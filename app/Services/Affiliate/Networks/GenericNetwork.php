<?php

namespace App\Services\Affiliate\Networks;

use Illuminate\Http\Request;

/**
 * Red genérica de respaldo para tiendas con programa de afiliados que no
 * tienen integración dedicada (zara, asos, proveedor propio del feed...).
 * Firma genérica: token compartido en cabecera X-Affiliate-Token.
 */
class GenericNetwork extends AbstractAffiliateNetwork
{
    public function name(): string
    {
        return 'Genérica';
    }

    public function slug(): string
    {
        return 'generic';
    }

    public function verify(Request $request): bool
    {
        $secret = config('services.affiliate.generic_webhook_secret');

        if (! $secret) {
            return false;
        }

        return hash_equals($secret, (string) $request->header('X-Affiliate-Token'));
    }

    public function extractSubId(Request $request): ?string
    {
        return $request->input('sub_id') ?? $request->input('click_id');
    }

    public function extractCommission(Request $request): float
    {
        return (float) ($request->input('commission') ?? 0);
    }

    public function extractExternalOrderId(Request $request): ?string
    {
        return $request->input('order_id') ?? $request->input('order_number');
    }

    protected function tagParam(): string
    {
        return 'affid';
    }

    protected function subParam(): string
    {
        return 'sub_id';
    }
}
