<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
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
            return redirect()->route('cart.index')->with('error', 'Tu carrito está vacío.');
        }

        $data = $request->validate([
            'customer_name'    => ['required', 'string', 'max:120'],
            'customer_email'   => ['required', 'email', 'max:150'],
            'customer_phone'   => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:500'],
        ]);

        // Sincronizamos antes de calcular totales para que el pedido refleje
        // precios y stock actuales, no los del momento en que se añadió al carrito.
        $this->cart->sync();

        if ($this->cart->isEmpty()) {
            return back()->with('error', 'Algunos productos ya no están disponibles. Revisa tu carrito.');
        }

        $items    = $this->cart->content();
        $subtotal = $this->cart->subtotal();
        $shipping = $this->cart->shipping();
        $total    = $this->cart->total();

        try {
            $order = DB::transaction(function () use ($data, $items, $subtotal, $shipping, $total) {
                $order = Order::create($data + [
                    'user_id'  => auth()->id(),
                    'status'   => Order::STATUS_PENDING,
                    'subtotal' => $subtotal,
                    'shipping' => $shipping,
                    'total'    => $total,
                ]);

                foreach ($items as $item) {
                    // Validación atómica de stock: sólo decrementa si hay suficientes uds.
                    // Esto previene stock negativo por concurrencia o carrito obsoleto.
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

                return $order;
            });
        } catch (\RuntimeException $e) {
            Log::warning('Checkout rechazado por stock: '.$e->getMessage());
            return back()->with('error', $e->getMessage())->withInput();
        }

        // Vaciamos el carrito sólo tras confirmar la transacción.
        $this->cart->clear();

        // Email de confirmación (en cola para no ralentizar el redirect).
        try {
            $notifiable = $order->user ?? \App\Models\User::where('email', $order->customer_email)->first();
            if ($notifiable) {
                $notifiable->notify(new OrderConfirmation($order));
            }
        } catch (\Throwable $e) {
            // El email es no bloqueante: lo registramos y seguimos.
            Log::error('No se pudo enviar el email de confirmación del pedido #'.$order->id.': '.$e->getMessage());
        }

        return redirect()->route('checkout.success', $order)
                         ->with('success', '¡Pedido confirmado! Te hemos enviado un email con el resumen.');
    }

    public function success(Request $request, Order $order)
    {
        // FIX IDOR: sólo el cliente propietario o un administrador pueden
        // ver la página de éxito del pedido. Sin esto, cualquier usuario
        // autenticado podía ver cualquier pedido adivinando el ID.
        if ($order->user_id && $order->user_id !== auth()->id() && ! auth()->user()?->isAdmin()) {
            abort(403, 'No tienes permiso para ver este pedido.');
        }

        $order->load('items');

        return view('checkout.success', compact('order'));
    }
}
