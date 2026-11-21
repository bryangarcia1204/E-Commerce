<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Público
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/categorias/{category}', [ProductController::class, 'byCategory'])->name('categories.show');

// Carrito
Route::prefix('carrito')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/añadir/{product}', [CartController::class, 'add'])->name('add');
    Route::patch('/actualizar/{product}', [CartController::class, 'update'])->name('update');
    Route::delete('/eliminar/{product}', [CartController::class, 'remove'])->name('remove');
    Route::post('/vaciar', [CartController::class, 'clear'])->name('clear');
});

// Checkout (requiere login)
Route::middleware('auth')->prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::post('/', [CheckoutController::class, 'store'])->name('store');
    Route::get('/exito/{order}', [CheckoutController::class, 'success'])->name('success');
});

// Breeze: dashboard de usuario
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Breeze: perfil
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Panel de administración
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');
    Route::resource('products', AdminProductController::class)->except(['show']);
});

require __DIR__.'/auth.php';