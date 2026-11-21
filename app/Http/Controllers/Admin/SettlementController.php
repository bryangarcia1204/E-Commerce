<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Services\SettlementService;
use Illuminate\Http\Request;

/**
 * Gestión del libro de liquidaciones dropshipping (panel admin).
 *
 * El admin ve y gestiona el flujo de caja pendiente (por cobrar / por pagar /
 * comisiones). Las filas contienen order_id, user_id, tipo, importe y estado:
 * NO exponen secretos del usuario (tokens de pago, contraseñas, saldo de
 * billetera). Marcar una comisión como liquidada se hace vía
 * SettlementService::settleCommission().
 */
class SettlementController extends Controller
{
    public function __construct(
        private SettlementService $settlements,
        private \App\Services\CacheService $cache,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Settlement::class);

        $query = Settlement::with('order')->latest();

        if ($request->filled('type')) {
            $query->ofType($request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $settlements = $query->paginate(25)->withQueryString();

        return view('admin.settlements.index', [
            'settlements' => $settlements,
            'types'   => Settlement::TYPES,
            'statuses' => Settlement::STATUSES,
        ]);
    }

    /**
     * Marca una comisión pendiente como liquidada (la tienda confirmó la
     * conversión vía postback). Pre-producción: se hace a mano desde aquí.
     */
    public function settle(Request $request, Settlement $settlement)
    {
        $this->authorize('update', $settlement);

        if ($settlement->type !== Settlement::TYPE_COMMISSION_OWED) {
            return back()->with('error', 'Sólo las comisiones se liquidan desde aquí.');
        }
        if ($settlement->status !== Settlement::STATUS_PENDING) {
            return back()->with('error', 'El asiento ya no está pendiente.');
        }

        $this->settlements->settleCommission(
            $settlement->order,
            $request->input('external_ref')
        );

        // Liquidar una comisión cambia los agregados pendientes del dashboard.
        $this->cache->bumpVersion('dashboard');

        return back()->with('success', 'Comisión marcada como liquidada.');
    }
}
