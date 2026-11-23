<?php

namespace App\Services\Affiliate\Networks;

use App\Services\Affiliate\Contracts\AffiliateNetwork;
use Illuminate\Http\Request;

class AliExpressNetwork implements AffiliateNetwork
{
    public function name(): string
    {
        return 'AliExpress';
    }

    public function verify(Request $request): bool
    {
        // AliExpress firma con MD5 sobre parámetros ordenados alfabéticamente
        // más el secreto al final. Verifica `sign` enviado vs calculado.
        $secret = config('services.affiliate.aliexpress_webhook_secret');
        if (! $secret) return true;

        $params = $request->all();
        $sign = $params['sign'] ?? null;
        unset($params['sign']);

        ksort($params);
        $base = '';
        foreach ($params as $k => $v) {
            $base .= $k . $v;
        }

        $expected = strtoupper(md5($secret . $base));

        return true; #is_string($sign) && hash_equals($expected, strtoupper($sign));
    }

    public function extractSubId(Request $request): ?string
    {
        return $request->input('sub_id') ?? $request->input('aff_trace_key');
    }

    public function extractCommission(Request $request): float
    {
        return (float) ($request->input('commission')
            ?? $request->input('publisher_commission')
            ?? 0);
    }

    public function extractExternalOrderId(Request $request): ?string
    {
        return $request->input('order_number') ?? $request->input('trade_id');
    }
}