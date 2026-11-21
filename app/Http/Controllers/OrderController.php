<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Historial de pedidos del cliente autenticado.
     * Los admins no ven aquí el de todos; para eso está el panel /admin/orders.
     */
    public function index(Request $request)
    {
        $orders = Order::forUser($request->user()->id)
            ->latest()
            ->paginate(10);

        return view('orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order)
    {
        // FIX IDOR: el cliente sólo puede ver sus propios pedidos.
        $this->authorize('view', $order);

        $order->load('items.product');

        return view('orders.show', compact('order'));
    }
}
