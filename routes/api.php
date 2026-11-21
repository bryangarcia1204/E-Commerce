<?php

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Público
Route::get('/products', function () {
    return Product::active()->with('category')->paginate(20);
});

Route::get('/products/{product}', function (Product $product) {
    abort_unless($product->is_active, 404);
    return $product->load('category', 'images');
});

// Autenticado
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    // Solo admin
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', function () {
            return [
                'products' => \App\Models\Product::count(),
                'orders'   => \App\Models\Order::count(),
                'users'    => \App\Models\User::where('role', 'customer')->count(),
                'revenue'  => \App\Models\Order::where('status', '!=', 'cancelled')->sum('total'),
            ];
        });
    });
});