<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private SettlementService $settlements,
    ) {}

    public function index()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Tu carrito está vacío.');
        }

        return view('checkout.index', [
            'items'    => $this->cart->content(),
            'subtotal' => $this->cart->subtotal(),
            'shipping' => $this->cart->shipping(),
            'total'    => $this->cart->total(),
        ]);
    }

    public function store(Request $request)
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $data = $request->validate([
            'customer_name'    => ['required', 'string', 'max:120'],
            'customer_email'   => ['required', 'email', 'max:150'],
            'customer_phone'   => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
        ]);

        $items    = $this->cart->content();
        $subtotal = $this->cart->subtotal();
        $shipping = $this->cart->shipping();
        $total    = $this->cart->total();

        // Cargamos los productos para saber cuáles son dropshipping.
        $productIds = array_column($items, 'id');
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Importe que cobramos nosotros = subtotal de productos PROPIOS
        // (no dropshipping). Los dropshipping se pagan en la tienda externa.
        $ownSubtotal = 0.0;
        $marginTotal = 0.0;
        $commissionTotal = 0.0;
        foreach ($items as $item) {
            $p = $products->get($item['id']);
            if ($p && $p->is_dropshipping) {
                // Margen + comisión que ganamos al canalizar la venta.
                $marginTotal += $p->unit_profit * $item['quantity'];
                $commissionTotal += $p->unit_commission * $item['quantity'];
            } else {
                $ownSubtotal += $item['price'] * $item['quantity'];
                // Los productos propios también pueden tener comisión si son
                // de afiliación sin dropshipping; por defecto 0.
                if ($p) {
                    $commissionTotal += $p->unit_commission * $item['quantity'];
                }
            }
        }

        // Si paga con billetera: debe tener saldo para cubrir la parte que
        // cobramos nosotros (ownSubtotal + envío). La parte dropshipping no
        // se debita (se paga en la tienda externa).
        $chargeable = $ownSubtotal + $shipping;
        if ($data['payment_method'] === User::PAYMENT_WALLET) {
            if (! $user->canAffordWithWallet($chargeable)) {
                return back()
                    ->with('error', 'Saldo insuficiente en tu billetera para este pedido. Faltan '.number_format($chargeable - (float) $user->wallet_balance, 2, ',', '.').' €.')
                    ->withInput();
            }
        }

        $paymentStatus = match ($data['payment_method']) {
            User::PAYMENT_WALLET => Order::PAYMENT_PAID,    // se debita ahora
            User::PAYMENT_COD => Order::PAYMENT_PENDING,    // paga al recibir
            default => Order::PAYMENT_PENDING,              // tarjeta/PayPal: pasarela externa
        };

        try {
            $order = DB::transaction(function () use (
                $data, $items, $subtotal, $shipping, $total,
                $user, $savedPm, $products, $marginTotal, $commissionTotal,
                $chargeable, $paymentStatus
            ) {
                $order = Order::create([
                    'user_id'           => $user->id,
                    'payment_method_id' => $savedPm?->id,
                    'customer_name'     => $data['customer_name'],
                    'customer_email'    => $data['customer_email'],
                    'customer_phone'    => $data['customer_phone'] ?? null,
                    'shipping_address'  => $data['shipping_address'],
                    'payment_method'    => $data['payment_method'],
                    'payment_status'    => $paymentStatus,
                    'status'            => Order::STATUS_PENDING,
                    'subtotal'          => $subtotal,
                    'shipping'          => $shipping,
                    'total'             => $total,
                    'margin_total'      => $marginTotal,
                    'commission_total'  => $commissionTotal,
                ]);

                foreach ($items as $item) {
                    $p = $products->get($item['id']);

                    if ($p && $p->is_dropshipping) {
                        // Dropshipping: NO tocamos stock local (lo gestiona la
                        // tienda externa). El item queda registrado para que el
                        // usuario lo complete en la tienda vía botón de afiliado.
                        $order->items()->create([
                            'product_id'   => $item['id'],
                            'product_name' => $item['name'],
                            'price'        => $item['price'],
                            'quantity'     => $item['quantity'],
                            'total'        => $item['price'] * $item['quantity'],
                        ]);
                        continue;
                    }

                    // Producto propio: decremento atómico de stock anti-concurrencia.
                    $updated = Product::where('id', $item['id'])
                        ->where('stock', '>=', $item['quantity'])
                        ->decrement('stock', $item['quantity']);

                    if (! $updated) {
                        $product = Product::withTrashed()->find($item['id']);
                        throw new \RuntimeException(sprintf(
                            'Sin stock suficiente para "%s". Solicitado: %d, disponible: %d.',
                            $product?->name ?? "ID {$item['id']}",
                            $item['quantity'],
                            $product?->stock ?? 0,
                        ));
                    }

                    $order->items()->create([
                        'product_id'   => $item['id'],
                        'product_name' => $item['name'],
                        'price'        => $item['price'],
                        'quantity'     => $item['quantity'],
                        'total'        => $item['price'] * $item['quantity'],
                    ]);
                }

                // Pago con billetera: debitamos ahora la parte cobrable.
                if ($data['payment_method'] === User::PAYMENT_WALLET && $chargeable > 0) {
                    $user->applyWalletMovement(
                        direction: 'debit',
                        type: 'purchase',
                        amount: $chargeable,
                        description: 'Pedido #'.$order->number,
                        source: $order,
                    );
                }

            return $order;
        });

        $this->cart->clear();

        return redirect()->route('checkout.success', $order);
    }

    public function success(Order $order)
    {
        $order->load('items');
        return view('checkout.success', compact('order'));
    }
}