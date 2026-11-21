<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart) {}

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

        // El saved_payment_method_id debe pertenecer al usuario (anti IDOR):
        // la policy lo cubre, pero validamos aquí para fallar rápido.
        $savedPm = null;
        if (! empty($data['saved_payment_method_id'])) {
            $savedPm = $user->paymentMethods()->find($data['saved_payment_method_id']);
            if (! $savedPm) {
                return back()->with('error', 'El método de pago seleccionado no es válido.')->withInput();
            }
        }

        // Sincronizamos antes de calcular totales para que el pedido refleje
        // precios y stock actuales, no los del momento en que se añadió al carrito.
        $this->cart->sync();

        if ($this->cart->isEmpty()) {
            return back()->with('error', 'Algunos productos ya no están disponibles. Revisa tu carrito.')->withInput();
        }

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
        } catch (\RuntimeException $e) {
            Log::warning('Checkout rechazado por stock: '.$e->getMessage());
            return back()->with('error', $e->getMessage())->withInput();
        }

        // Vaciamos el carrito sólo tras confirmar la transacción.
        $this->cart->clear();

        // Persistimos los datos de envío en el perfil del usuario si no los
        // tenía, para acelerar la próxima compra (sólo si vienen informados).
        if (! empty($data['shipping_address']) && $user->default_shipping_address !== $data['shipping_address']) {
            $user->default_shipping_address = $data['shipping_address'];
            $user->phone = $user->phone ?: ($data['customer_phone'] ?? null);
            $user->save();
        }

        // Email de confirmación (en cola para no ralentizar el redirect).
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
