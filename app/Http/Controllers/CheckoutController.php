<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use App\Services\CacheService;
use App\Services\CartService;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\WalletService;

class CheckoutController extends Controller
{
    public function __construct(
        private CartService $cart,
        private SettlementService $settlements,
        private CacheService $cache,
    ) {}

    public function index()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Tu carrito está vacío.');
        }

        // Refrescamos el carrito desde la BD para mostrar precios y stock reales.
        $this->cart->sync();

        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Algunos productos de tu carrito ya no están disponibles.');
        }

        $user = auth()->user()->load('paymentMethods');

        // Pre-poblamos los datos del formulario desde el perfil del usuario
        // para acelerar la compra (el usuario puede editar sobre la marcha).
        $defaults = [
            'customer_name'    => old('customer_name', $user->name),
            'customer_email'   => old('customer_email', $user->email),
            'customer_phone'   => old('customer_phone', $user->phone ?? ''),
            'shipping_address' => old('shipping_address', $user->default_shipping_address ?? ''),
            'payment_method'   => old('payment_method', $user->preferred_payment_method ?? User::PAYMENT_CARD),
        ];

        // Cargamos los productos del carrito para detectar cuáles son
        // dropshipping (necesario para el aviso y los botones de tienda).
        $productIds = array_column($this->cart->content(), 'id');
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $hasDropshipping = $products->contains(fn ($p) => $p->is_dropshipping);

        // Para el selector de pago: métodos guardados del usuario + billetera
        // + contra reembolso. El "wallet" muestra el saldo disponible.
        $savedMethods = $user->paymentMethods()->orderByDesc('is_default')->get();

        return view('checkout.index', [
            'items'         => $this->cart->content(),
            'subtotal'      => $this->cart->subtotal(),
            'shipping'      => $this->cart->shipping(),
            'total'         => $this->cart->total(),
            'defaults'      => $defaults,
            'user'          => $user,
            'savedMethods'  => $savedMethods,
            'products'      => $products,
            'hasDropshipping' => $hasDropshipping,
        ]);
    }

    public function store(Request $request)
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Tu carrito está vacío.');
        }

        $user = auth()->user()->load('paymentMethods');

        $data = $request->validate([
            'customer_name'    => ['required', 'string', 'max:120'],
            'customer_email'   => ['required', 'email', 'max:150'],
            'customer_phone'   => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'payment_method'   => ['required', 'string', 'in:'.implode(',', array_keys(User::PAYMENTS))],
            'saved_payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
        ]);

        // El saved_payment_method_id debe pertenecer al usuario (anti IDOR).
        $savedPm = null;
        if (! empty($data['saved_payment_method_id'])) {
            $savedPm = $user->paymentMethods()->find($data['saved_payment_method_id']);
            if (! $savedPm) {
                return back()->with('error', 'El método de pago seleccionado no es válido.')->withInput();
            }
        }

        $this->cart->sync();

        if ($this->cart->isEmpty()) {
            return back()->with('error', 'Algunos productos ya no están disponibles. Revisa tu carrito.')->withInput();
        }

        $items    = $this->cart->content();
        $subtotal = $this->cart->subtotal();
        $shipping = $this->cart->shipping();
        $total    = $this->cart->total();

        $productIds = array_column($items, 'id');
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        // Separar productos propios vs dropshipping.
        $ownSubtotal     = 0.0;
        $marginTotal     = 0.0;
        $commissionTotal = 0.0;
        $payableSupplier = 0.0;

        foreach ($items as $item) {
            $p = $products->get($item['id']);

            if ($p && $p->is_dropshipping) {
                $marginTotal     += $p->unit_profit * $item['quantity'];
                $commissionTotal += $p->unit_commission * $item['quantity'];
                continue;
            }

            $ownSubtotal += $item['price'] * $item['quantity'];

            if ($p) {
                $commissionTotal += $p->unit_commission * $item['quantity'];
                if ($p->cost_price !== null) {
                    $payableSupplier += (float) $p->cost_price * $item['quantity'];
                }
            }
        }

        // Lo que cobramos nosotros: productos propios + envío.
        // (Dropshipping lo paga el usuario en la tienda externa.)
        $chargeable = round($ownSubtotal + $shipping, 2);

        // Check rápido de saldo. El débito real se hará dentro de la transacción.
        if ($data['payment_method'] === User::PAYMENT_WALLET && ! $user->canAffordWithWallet($chargeable)) {
            $faltan = max(0, $chargeable - $user->wallet_balance);
            return back()
                ->with('error', 'Saldo insuficiente en tu billetera. Faltan '.number_format($faltan, 2, ',', '.').' €.')
                ->withInput();
        }

        $paymentStatus = match ($data['payment_method']) {
            User::PAYMENT_WALLET => Order::PAYMENT_PAID,
            User::PAYMENT_COD    => Order::PAYMENT_PENDING,
            default              => Order::PAYMENT_PENDING,
        };

        try {
            $order = DB::transaction(function () use (
                $data, $items, $subtotal, $shipping, $total,
                $user, $savedPm, $products, $marginTotal, $commissionTotal,
                $chargeable, $paymentStatus, $payableSupplier
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
                        $order->items()->create([
                            'product_id'   => $item['id'],
                            'product_name' => $item['name'],
                            'price'        => $item['price'],
                            'quantity'     => $item['quantity'],
                            'total'        => $item['price'] * $item['quantity'],
                        ]);
                        continue;
                    }

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

                // Débito de billetera: ÚNICO punto, DENTRO de la transacción,
                // por $chargeable (no $total). Si algo falla arriba, se revierte.
                if ($data['payment_method'] === User::PAYMENT_WALLET && $chargeable > 0) {
                    $user->applyWalletMovement(
                        direction: 'debit',
                        type: 'purchase',
                        amount: $chargeable,
                        description: 'Pedido #'.$order->number,
                        source: $order,
                        idempotencyKey: 'order-'.$order->number.'-payment',
                        meta: ['order_id' => $order->id],
                    );
                }

                $this->settlements->createForOrder($order, [
                    'chargeable'       => $chargeable,
                    'commission_total' => $commissionTotal,
                    'payable_supplier' => $payableSupplier,
                    'wallet_paid'      => $data['payment_method'] === User::PAYMENT_WALLET,
                ]);

                return $order;
            });
        } catch (\RuntimeException $e) {
            Log::warning('Checkout rechazado: '.$e->getMessage());
            return back()->with('error', $e->getMessage())->withInput();
        }

        $this->cache->bumpVersion('dashboard');
        $this->cart->clear();

        if (! empty($data['shipping_address']) && $user->default_shipping_address !== $data['shipping_address']) {
            $user->default_shipping_address = $data['shipping_address'];
            $user->phone = $user->phone ?: ($data['customer_phone'] ?? null);
            $user->save();
        }

        try {
            $user->notify(new OrderConfirmation($order));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el email de confirmación del pedido #'.$order->id.': '.$e->getMessage());
        }

        return redirect()->route('checkout.success', $order)
                        ->with('success', '¡Pedido registrado! Te hemos enviado un email con el resumen.');
    }

    public function success(Request $request, Order $order)
    {
        // FIX IDOR: sólo el cliente propietario o un administrador pueden
        // ver la página de éxito del pedido.
        if ($order->user_id && $order->user_id !== auth()->id() && ! auth()->user()?->isAdmin()) {
            abort(403, 'No tienes permiso para ver este pedido.');
        }

        $order->load('items.product');

        return view('checkout.success', compact('order'));
    }
}
