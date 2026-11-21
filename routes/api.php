<?php

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública (read-only, throttled)
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:api')->group(function () {
    Route::get('/products', function () {
        return Product::active()
            ->with('category:id,slug,name')
            ->paginate(20)
            ->through(fn ($p) => [
                'id'           => $p->id,
                'name'         => $p->name,
                'slug'         => $p->slug,
                'price'        => (float) $p->price,
                'discount_price' => $p->discount_price !== null ? (float) $p->discount_price : null,
                'current_price'  => $p->current_price,
                'stock'        => $p->stock,
                'image_url'    => $p->image_url,
                'category'     => $p->category?->only(['slug', 'name']),
            ]);
    });

    Route::get('/products/{product}', function (Product $product) {
        abort_unless($product->is_active, 404);

        return $product->load('category:id,slug,name', 'images:id,product_id,path,sort_order')
            ->only(['id', 'name', 'slug', 'description', 'price', 'discount_price', 'stock', 'image_url']);
    });
});

/*
|--------------------------------------------------------------------------
| API autenticada (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // No devolvemos el modelo completo para no filtrar campos sensibles.
    Route::get('/user', fn (Request $request) => [
        'id'    => $request->user()->id,
        'name'  => $request->user()->name,
        'email' => $request->user()->email,
        'role'  => $request->user()->role,
    ]);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats', function () {
            return [
                'products' => Product::count(),
                'orders'   => Order::count(),
                'users'    => \App\Models\User::where('role', 'customer')->count(),
                'revenue'  => (float) Order::where('status', '!=', Order::STATUS_CANCELLED)->sum('total'),
            ];
        });
    });
});
