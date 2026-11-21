<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index()
    {
        return view('cart.index', [
            'items'    => $this->cart->content(),
            'subtotal' => $this->cart->subtotal(),
            'shipping' => $this->cart->shipping(),
            'total'    => $this->cart->total(),
        ]);
    }

    public function add(Request $request, Product $product)
    {
        $qty = max(1, (int) $request->input('quantity', 1));

        if ($product->stock < 1) {
            return back()->with('error', 'Producto sin stock.');
        }

        $this->cart->add($product, $qty);

        return back()->with('success', "«{$product->name}» añadido al carrito.");
    }

    public function update(Request $request, Product $product)
    {
        $qty = (int) $request->input('quantity', 1);
        $this->cart->update($product->id, $qty);
        return back();
    }

    public function remove(Product $product)
    {
        $this->cart->remove($product->id);
        return back()->with('success', 'Producto eliminado del carrito.');
    }

    public function clear()
    {
        $this->cart->clear();
        return back();
    }
}