<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Services\Affiliate\Networks\AliExpressNetwork;
use App\Services\Affiliate\Networks\AmazonNetwork;
use App\Services\Affiliate\Networks\SheinNetwork;
use App\Services\Affiliate\Contracts\AffiliateNetwork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AffiliateWebhookController extends Controller
{
    /** Mapa red → clase. Registro explícito, sin auto-discovery mágica. */
    private array $networks = [
        'amazon'      => AmazonNetwork::class,
        'aliexpress'  => AliExpressNetwork::class,
        'shein'       => SheinNetwork::class,
    ];

    public function handle(Request $request, string $network)
    {
        $handler = $this->resolveHandler($network);

        if (! $handler) {
            return response()->json(['error' => 'Unknown network'], 404);
        }

        if (! $handler->verify($request)) {
            Log::warning('Affiliate webhook: firma inválida', [
                'network' => $network,
                'ip'      => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $subId = $handler->extractSubId($request);

        if (! $subId) {
            Log::warning('Affiliate webhook: falta sub_id', ['network' => $network]);
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

        $commission = $handler->extractCommission($request);

        $click->update([
            'status'             => 'converted',
            'converted_at'       => now(),
            'commission_amount'  => $commission,
            'external_order_id'  => $externalOrderId,
            'webhook_payload'    => $request->all(),
        ]);

        Log::info('Affiliate webhook: conversión registrada', [
            'click_id'   => $click->id,
            'network'    => $network,
            'commission' => $commission,
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function resolveHandler(string $network): ?AffiliateNetwork
    {
        $class = $this->networks[strtolower($network)] ?? null;
        return $class ? app($class) : null;
    }
}