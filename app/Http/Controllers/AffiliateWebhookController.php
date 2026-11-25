<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Services\Affiliate\AffiliateNetworkManager;
use App\Services\Affiliate\Contracts\AffiliateNetwork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AffiliateWebhookController extends Controller
{
    public function __construct(private AffiliateNetworkManager $networks) {}

    public function handle(Request $request, string $network)
    {
        // El slug llega por la URL: se normaliza (case-insensitive) y se
        // resuelve contra el registro central de redes.
        $handler = $this->resolveHandler($network);

        if (! $handler) {
            return response()->json(['error' => 'Unknown network'], 404);
        }

        if (! $handler->verify($request)) {
            Log::warning('Affiliate webhook: firma inválida', [
                'network' => $handler->slug(),
                'ip'      => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $subId = $handler->extractSubId($request);

        if (! $subId) {
            Log::warning('Affiliate webhook: falta sub_id', ['network' => $handler->slug()]);
            return response()->json(['error' => 'Missing sub_id'], 422);
        }

        $click = AffiliateClick::where('sub_id', $subId)->first();

        if (! $click) {
            // 200 pero log: es habitual que lleguen conversiones de clics antiguos
            // que ya no están (o de otros sitios). No queremos que la red reintente.
            Log::info('Affiliate webhook: clic no encontrado', ['sub_id' => $subId]);
            return response()->json(['status' => 'ignored']);
        }

        // Idempotencia: si ya está convertido con el mismo order_id, no dupliques
        $externalOrderId = $handler->extractExternalOrderId($request);
        if ($click->status === 'converted'
            && $click->external_order_id
            && $click->external_order_id === $externalOrderId) {
            return response()->json(['status' => 'already_processed']);
        }

        // Un clic rechazado manualmente en conciliación no se re-convierte.
        if ($click->status === 'rejected') {
            Log::info('Affiliate webhook: clic rechazado en conciliación, se ignora', [
                'click_id' => $click->id,
            ]);
            return response()->json(['status' => 'ignored']);
        }

        $commission = $handler->extractCommission($request);

        $click->update([
            'status'             => 'converted',
            'converted_at'       => now(),
            'commission_amount'  => $commission > 0 ? $commission : $click->commission_amount,
            'external_order_id'  => $externalOrderId,
            // Normalizamos la red por si el clic se registró con un valor
            // antiguo capitalizado ('Amazon' → 'amazon').
            'network'            => $handler->slug(),
            'webhook_payload'    => $request->all(),
        ]);

        Log::info('Affiliate webhook: conversión registrada', [
            'click_id'   => $click->id,
            'network'    => $handler->slug(),
            'commission' => $commission,
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function resolveHandler(string $network): ?AffiliateNetwork
    {
        // bySlug() sólo acepta redes con integración dedicada: las redes
        // genéricas no tienen webhook propio.
        return $this->networks->bySlug($network);
    }
}
