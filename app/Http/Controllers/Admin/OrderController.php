<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CacheService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private CacheService $cache) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class); // admin gate (re-check)

        $query = Order::with('items')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('customer_name', 'like', "%{$q}%")
                   ->orWhere('customer_email', 'like', "%{$q}%")
                   ->orWhere('id', $q);
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('admin.orders.index', [
            'orders'  => $orders,
            'statuses' => Order::STATUSES,
        ]);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load('items.product');

        return view('admin.orders.show', [
            'order'    => $order,
            'statuses' => Order::STATUSES,
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
        ]);

        $order->update($data);

        // Cambió el estado (p.ej. a cancelled): invalida agregados del dashboard.
        $this->cache->bumpVersion('dashboard');

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Estado del pedido actualizado.');
    }
}
