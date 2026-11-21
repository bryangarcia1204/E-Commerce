<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart) {}

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

        $order = DB::transaction(function () use ($data, $items, $subtotal, $shipping, $total) {
            $order = Order::create($data + [
                'user_id'  => auth()->id(),
                'status'   => 'pending',
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total'    => $total,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id'   => $item['id'],
                    'product_name' => $item['name'],
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'total'        => $item['price'] * $item['quantity'],
                ]);

                Product::where('id', $item['id'])->decrement('stock', $item['quantity']);
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