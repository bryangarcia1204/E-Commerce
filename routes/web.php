<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Admin\AffiliateController as AdminAffiliateController;
use App\Http\Controllers\Account\DashboardController as AccountDashboard;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Admin\WalletController as AdminWalletController;
use App\Http\Controllers\AffiliateRedirectController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Account\WalletController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AffiliateWebhookController;

Route::post('/webhooks/affiliate/{network}', [AffiliateWebhookController::class, 'handle'])
    ->name('affiliate.webhook');

/*
|--------------------------------------------------------------------------
| Rutas públicas (catálogo + carrito)
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');

// Catálogo: hot paths cacheados en Redis + throttle:storefront (120/min,
// contador en Redis) para mitigar scrapers que saturen el catálogo.
Route::middleware('throttle:storefront')->group(function () {
    Route::get('/productos', [ProductController::class, 'index'])->name('products.index');
    Route::get('/productos/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/categorias/{category}', [ProductController::class, 'byCategory'])->name('categories.show');
});

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
    Route::get('/novedades', [PageController::class, 'news'])->middleware('throttle:storefront')->name('novedades');
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
| Favoritos / wishlist (auth, persistente en BD)
| Las tarjetas y el detalle se cachean como HTML, así que el botón corazón
| es neutro (data-wishlist) y el JS del layout gestiona estado + toggle por
| fetch. /favoritos/ids va antes que /{product} para que el slug no capture
| "ids".
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->name('wishlist.')->group(function () {
    Route::get('/favoritos', [WishlistController::class, 'index'])->middleware('throttle:storefront')->name('index');
    Route::get('/favoritos/ids', [WishlistController::class, 'ids'])->name('ids');
    Route::post('/favoritos/{product}', [WishlistController::class, 'toggle'])->middleware('throttle:web')->name('toggle');
    Route::delete('/favoritos/{product}', [WishlistController::class, 'destroy'])->middleware('throttle:web')->name('destroy');
});

/*
|--------------------------------------------------------------------------
| Breeze: dashboard de usuario y perfil
|--------------------------------------------------------------------------
*/
Route::redirect('/dashboard', '/cuenta')
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
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

    // Libro de liquidaciones dropshipping (flujo de caja pendiente).
    Route::get('/settlements', [AdminSettlementController::class, 'index'])->name('settlements.index');
    Route::post('/settlements/{settlement}/settle', [AdminSettlementController::class, 'settle'])->name('settlements.settle');

    // Admin · Afiliación
    Route::get('/afiliacion', [AdminAffiliateController::class, 'index'])->name('affiliate.index');
    Route::get('/afiliacion/export', [AdminAffiliateController::class, 'export'])->name('affiliate.export');
    Route::delete('/afiliacion/{id}', [AdminAffiliateController::class, 'destroy'])->name('affiliate.destroy');

    Route::get('/afiliacion/conciliacion', [AdminAffiliateController::class, 'reconciliation'])->name('affiliate.reconciliation');
    Route::patch('/afiliacion/{click}/marcar-pagado', [AdminAffiliateController::class, 'markAsPaid'])->name('affiliate.markPaid');
    Route::patch('/afiliacion/{click}/rechazar', [AdminAffiliateController::class, 'reject'])->name('affiliate.reject');

    Route::get('/billeteras', [AdminWalletController::class, 'index'])->name('wallets.index');
    Route::get('/billeteras/{wallet}', [AdminWalletController::class, 'show'])->name('wallets.show');
    Route::post('/billeteras/{wallet}/ajuste', [AdminWalletController::class, 'adjust'])->name('wallets.adjust');
});

// Área del cliente
Route::middleware('auth')->prefix('cuenta')->name('account.')->group(function () {
    Route::get('/', [AccountDashboard::class, 'index'])->name('dashboard');
    Route::get('/pedidos', [AccountOrderController::class, 'index'])->name('orders.index');
    Route::get('/pedidos/{order}', [AccountOrderController::class, 'show'])->name('orders.show');
    Route::get('/perfil', [AccountDashboard::class, 'editProfile'])->name('profile.edit');
    Route::patch('/perfil', [AccountDashboard::class, 'updateProfile'])->name('profile.update');
    Route::patch('/perfil/password', [AccountDashboard::class, 'updatePassword'])->name('password.update');
    Route::delete('/perfil', [AccountDashboard::class, 'destroyAccount'])->middleware('auth')->name('profile.destroy');
    Route::get('/billetera', [WalletController::class, 'index'])->name('wallet.index');
    });

require __DIR__.'/auth.php';
