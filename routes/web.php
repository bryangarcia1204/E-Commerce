<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\AffiliateRedirectController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas públicas (catálogo + carrito)
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
Route::get('/productos/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/categorias/{category}', [ProductController::class, 'byCategory'])->name('categories.show');

// Carrito: la escritura requiere CSRF; añadimos throttle:web para mitigar abuso.
Route::middleware('throttle:web')->prefix('carrito')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/añadir/{product}', [CartController::class, 'add'])->name('add');
    Route::patch('/actualizar/{product}', [CartController::class, 'update'])->name('update');
    Route::delete('/eliminar/{product}', [CartController::class, 'remove'])->name('remove');
    Route::post('/vaciar', [CartController::class, 'clear'])->name('clear');
});

/*
|--------------------------------------------------------------------------
| Páginas institucionales (footer) + formulario de contacto
|--------------------------------------------------------------------------
*/
Route::name('pages.')->group(function () {
    Route::get('/envios', [PageController::class, 'shipping'])->name('envios');
    Route::get('/devoluciones', [PageController::class, 'returns'])->name('devoluciones');
    Route::get('/privacidad', [PageController::class, 'privacy'])->name('privacidad');
    Route::get('/terminos', [PageController::class, 'terms'])->name('terminos');
    Route::get('/sobre-nosotros', [PageController::class, 'about'])->name('nosotros');
    Route::get('/novedades', [PageController::class, 'news'])->name('novedades');
});

// Contacto: el GET es público, el POST va con throttle:web anti-spam.
Route::get('/contacto', [ContactController::class, 'create'])->name('pages.contacto');
Route::post('/contacto', [ContactController::class, 'store'])
    ->middleware('throttle:web')
    ->name('contacto.store');

/*
|--------------------------------------------------------------------------
| Afiliación: punto único de salida a tiendas externas (dropshipping).
| Público (si el usuario está logueado se vincula al clic para auditoría).
|--------------------------------------------------------------------------
*/
Route::get('/ir/{product}', [AffiliateRedirectController::class, 'redirect'])
    ->name('affiliate.redirect');

/*
|--------------------------------------------------------------------------
| Checkout (requiere auth)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::post('/', [CheckoutController::class, 'store'])->name('store');
    Route::get('/exito/{order}', [CheckoutController::class, 'success'])->name('success');
});

/*
|--------------------------------------------------------------------------
| Historial de pedidos del cliente
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/mis-pedidos', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/mis-pedidos/{order}', [OrderController::class, 'show'])->name('orders.show');
});

/*
|--------------------------------------------------------------------------
| Breeze: dashboard de usuario y perfil
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Métodos de pago guardados. La autorización por usuario la hace
    // PaymentMethodPolicy en setDefault/destroy (anti IDOR).
    Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::patch('/payment-methods/{paymentMethod}/default', [PaymentMethodController::class, 'setDefault'])->name('payment-methods.default');
    Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
});

/*
|--------------------------------------------------------------------------
| Panel de administración (auth + admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboard::class, 'index'])->name('dashboard');

    // Gestión de catálogo
    Route::resource('products', AdminProductController::class)->except(['show']);

    // Gestión de pedidos
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
});

require __DIR__.'/auth.php';
