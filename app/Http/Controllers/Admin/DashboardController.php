<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'totalProducts' => Product::count(),
            'totalOrders'   => Order::count(),
            'totalUsers'    => User::where('role', User::ROLE_CUSTOMER)->count(),
            // Ingresos: excluimos pedidos cancelados. Cast a float para que la
            // vista no reciba un string desde SUM() y number_format() funcione.
            'revenue'       => (float) Order::where('status', '!=', Order::STATUS_CANCELLED)->sum('total'),
            'recentOrders'  => Order::with('items')->latest()->take(5)->get(),
        ]);
    }
}
