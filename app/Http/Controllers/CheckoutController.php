<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use App\Services\CacheService;
use App\Services\CartService;
use App\Services\Payment\PaymentService;
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
        private PaymentService $payments,
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

        // Importe que cobramos nosotros (propios + envío): el que debe cubrir
        // la billetera/tarjeta. El dropshipping se paga en la tienda externa.
        $chargeable = $this->chargeableFor($this->cart->content(), $products);

        return view('checkout.index', [
            'items'         => $this->cart->content(),
            'subtotal'      => $this->cart->subtotal(),
            'shipping'      => $this->cart->shipping(),
            'total'         => $this->cart->total(),
            'chargeable'    => $chargeable,
            'defaults'      => $defaults,
            'user'          => $user,
            'savedMethods'  => $savedMethods,
            'isSandbox'     => $this->payments->isSandbox(),
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

        // Métodos de pago disponibles en checkout: tarjeta (Stripe real o
        // sandbox), billetera y contra reembolso. FIX: la vista ofrecía un
        // valor "external" que NO estaba en la lista de validación → el
        // checkout fallaba para todo menos billetera.
        $paymentMethods = [User::PAYMENT_CARD, User::PAYMENT_PAYPAL, User::PAYMENT_WALLET, User::PAYMENT_COD];

        $data = $request->validate([
            'customer_name'    => ['required', 'string', 'max:120'],
            'customer_email'   => ['required', 'email', 'max:150'],
            'customer_phone'   => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
            'payment_method'   => ['required', 'string', 'in:'.implode(',', $paymentMethods)],
            'saved_payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            // Tarjeta nueva (sólo si paga con tarjeta sin método guardado).
            'card_holder'   => ['nullable', 'required_with:card_number', 'string', 'max:120'],
            'card_number'   => ['nullable', 'string', 'max:25', 'regex:/^[\d\s]{13,25}$/'],
            'card_exp_month'=> ['nullable', 'required_with:card_number', 'integer', 'between:1,12'],
            'card_exp_year' => ['nullable', 'required_with:card_number', 'integer', 'min:24', 'max:2100'],
            'card_cvc'      => ['nullable', 'required_with:card_number', 'string', 'digits_between:3,4'],
            'save_card'     => ['nullable', 'boolean'],
        ]);

        // El saved_payment_method_id debe pertenecer al usuario (anti IDOR).
        $savedPm = null;
        if (! empty($data['saved_payment_method_id'])) {
            $savedPm = $user->paymentMethods()->find($data['saved_payment_method_id']);
            if (! $savedPm) {
                return back()->with('error', 'El método de pago seleccionado no es válido.')->withInput();
            }
        }

        // Si paga con tarjeta: o método guardado o tarjeta nueva completa.
        if ($data['payment_method'] === User::PAYMENT_CARD
            && ! $savedPm
            && empty($data['card_number'])) {
            return back()
                ->with('error', 'Introduce los datos de tu tarjeta o selecciona una tarjeta guardada.')
                ->withInput();
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

        // 1) El pedido se crea PRIMERO (patrón order-first): pedido con
        //    payment_status=pending + decremento de stock + débito de
        //    billetera, todo atómico en la transacción. El cobro con
        //    tarjeta se hace DESPUÉS del commit: si el banco lo rechaza,
        //    el pedido se cancela y el stock se repone abajo.
        $paymentStatus = match ($data['payment_method']) {
            User::PAYMENT_WALLET => Order::PAYMENT_PAID,
            // Tarjeta: pendiente hasta confirmar el cobro. Si el importe a
            // cobrar es 0 (carrito 100% dropshipping) no hay nada que cobrar.
            User::PAYMENT_CARD   => $chargeable > 0 ? Order::PAYMENT_PENDING : Order::PAYMENT_PAID,
            User::PAYMENT_COD    => Order::PAYMENT_PENDING,
            default              => Order::PAYMENT_PENDING, // PayPal: pendiente de confirmación
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

                // Asientos contables. Billetera o tarjeta-confirmada → el
                // "por cobrar del cliente" nace liquidado (paid_upfront).
                // La tarjeta se confirma justo después; si el banco la
                // rechaza, el pedido se cancela abajo y el asiento se
                // queda como pendiente anulado por el estado del pedido.
                $this->settlements->createForOrder($order, [
                    'chargeable'       => $chargeable,
                    'commission_total' => $commissionTotal,
                    'payable_supplier' => $payableSupplier,
                    'paid_upfront'     => $data['payment_method'] === User::PAYMENT_WALLET,
                ]);

                return $order;
            });
        } catch (\RuntimeException $e) {
            Log::warning('Checkout rechazado: '.$e->getMessage());
            return back()->with('error', $e->getMessage())->withInput();
        }

        // 2) Cobro real con tarjeta (Stripe o sandbox). Fuera de la
        //    transacción del pedido: si el banco rechaza, cancelamos el
        //    pedido y rep stock en lugar de revertir la BD a ciegas.
        //    Con importe 0 (todo dropshipping) no hay nada que cobrar.
        if ($data['payment_method'] === User::PAYMENT_CARD && $chargeable > 0) {
            $cardResult = $this->payments->chargeOrder(
                $order,
                $chargeable,
                [
                    'holder'    => $data['card_holder'] ?? null,
                    'number'    => $data['card_number'] ?? null,
                    'exp_month' => $data['card_exp_month'] ?? null,
                    'exp_year'  => $data['card_exp_year'] ?? null,
                    'cvc'       => $data['card_cvc'] ?? null,
                ],
                savedMethod: ($savedPm?->type === PaymentMethod::TYPE_CARD) ? $savedPm : null,
            );

            if (! $cardResult->success) {
                $this->failOrder($order, $products);

                return back()
                    ->with('error', $cardResult->errorMessage ?? 'El pago no pudo completarse.')
                    ->withInput();
            }

            // Cobro aceptado: pedido pagado + referencia de la transacción
            // (la usará el webhook de Stripe si la confirmación es asíncrona).
            $order->update([
                'payment_status' => Order::PAYMENT_PAID,
                'external_refs'  => [
                    'payment' => [
                        'gateway'        => $this->payments->gateway()->name(),
                        'transaction_id' => $cardResult->transactionId,
                        'amount'         => $chargeable,
                    ],
                ],
            ]);

            // El asiento "por cobrar del cliente" nació pendiente: lo liquidamos.
            $this->settlements->settleReceivable($order, $cardResult->transactionId);

            // Guardar la tarjeta como método de pago si el usuario lo pidió.
            if (! empty($data['card_number']) && ! empty($data['save_card'])) {
                try {
                    $this->payments->storeCardMethod(
                        $user->id,
                        [
                            'number'    => $data['card_number'],
                            'exp_month' => $data['card_exp_month'],
                            'exp_year'  => $data['card_exp_year'],
                        ],
                        makeDefault: ! $savedPm,
                    );
                } catch (\Throwable $e) {
                    // Best-effort: guardar la tarjeta no rompe el pedido.
                    Log::error('No se pudo guardar la tarjeta tras el pago: '.$e->getMessage());
                }
            }
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

    /**
     * Importe que cobra MODA: productos propios + envío (el dropshipping
     * se paga en la tienda externa). Se usa en la vista y en el cobro.
     */
    private function chargeableFor(array $items, \Illuminate\Support\Collection $products): float
    {
        $ownSubtotal = 0.0;

        foreach ($items as $item) {
            $p = $products->get($item['id']);

            if ($p && $p->is_dropshipping) {
                continue;
            }

            $ownSubtotal += $item['price'] * $item['quantity'];
        }

        return round($ownSubtotal + $this->cart->shipping(), 2);
    }

    /**
     * Cancela un pedido cuyo cobro con tarjeta fue rechazado y repone el
     * stock decrementado. El pedido queda visible (cancelado / pago
     * fallido) para auditoría, sin cobrar nada al cliente.
     */
    private function failOrder(Order $order, \Illuminate\Support\Collection $products): void
    {
        try {
            DB::transaction(function () use ($order, $products) {
                foreach ($order->items as $item) {
                    $p = $products->get($item->product_id);

                    if ($p && $p->is_dropshipping) {
                        continue;
                    }

                    Product::where('id', $item->product_id)
                        ->increment('stock', $item->quantity);
                }

                $order->update([
                    'status'         => Order::STATUS_CANCELLED,
                    'payment_status' => Order::PAYMENT_FAILED,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('No se pudo cancelar el pedido #'.$order->number.' tras un pago fallido: '.$e->getMessage());
        }
    }
}
