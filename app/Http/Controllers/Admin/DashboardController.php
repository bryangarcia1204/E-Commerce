<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Settlement;
use App\Models\User;
use App\Services\CacheService;

class DashboardController extends Controller
{
    public function __construct(private CacheService $cache) {}

    public function index()
    {
        // Agregados cacheados 60s en Redis (namespace 'dashboard'). El admin
        // recarga a menudo; sin caché cada visita suma ~7 queries de
        // SUM/COUNT. Invalida al crear pedidos o mutar liquidaciones. Si Redis
        // cae, CacheService sirve desde BD sin caché.
        return view('admin.dashboard', $this->cache->rememberVersioned('dashboard', 'main', function () {
            // Agregados financieros del libro de liquidaciones (dropshipping).
            // El admin ve el flujo de caja pendiente SIN tocar secretos
            // individuales (tokens de pago, billeteras de usuarios, etc.).
            $pendingReceivable = (float) Settlement::ofType(Settlement::TYPE_RECEIVABLE_CUSTOMER)
                ->where('status', Settlement::STATUS_PENDING)->sum('amount');
            $pendingCommission = (float) Settlement::ofType(Settlement::TYPE_COMMISSION_OWED)
                ->where('status', Settlement::STATUS_PENDING)->sum('amount');
            $pendingPayable = (float) Settlement::ofType(Settlement::TYPE_PAYABLE_SUPPLIER)
                ->where('status', Settlement::STATUS_PENDING)->sum('amount');

            // Margen + comisión ya realizados (no pendientes).
            $realizedMargin = (float) Order::where('status', '!=', Order::STATUS_CANCELLED)->sum('margin_total');
            $realizedCommission = (float) Order::where('status', '!=', Order::STATUS_CANCELLED)->sum('commission_total');

            return [
                'totalProducts' => Product::count(),
                'totalOrders'   => Order::count(),
                'totalUsers'    => User::where('role', User::ROLE_CUSTOMER)->count(),
                // Ingresos: excluimos pedidos cancelados. Cast a float para que la
                // vista no reciba un string desde SUM() y number_format() funcione.
                'revenue'       => (float) Order::where('status', '!=', Order::STATUS_CANCELLED)->sum('total'),
                'recentOrders'  => Order::with('items')->latest()->take(5)->get(),
                // Finanzas dropshipping (agregado, sin secretos).
                'pendingReceivable'   => $pendingReceivable,
                'pendingCommission'   => $pendingCommission,
                'pendingPayable'      => $pendingPayable,
                'realizedMargin'      => $realizedMargin,
                'realizedCommission'  => $realizedCommission,
            ];
        }));
    }
}
