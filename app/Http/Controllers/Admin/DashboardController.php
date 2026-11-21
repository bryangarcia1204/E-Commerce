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
            'revenue'       => Order::where('status', '!=', 'cancelled')->sum('total'),
            'recentOrders'  => Order::latest()->take(5)->get(),
        ]);
    }
}