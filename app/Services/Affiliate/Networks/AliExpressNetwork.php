<?php

namespace App\Services\Affiliate\Networks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AliExpressNetwork extends AbstractAffiliateNetwork
{
    public function name(): string
    {
        return 'AliExpress';
    }

    public function slug(): string
    {
        return 'aliexpress';
    }

    public function verify(Request $request): bool
    {
        // AliExpress Portals firma con MD5 sobre los parámetros ordenados
        // alfabéticamente (clave+valor concatenados) con el secreto al
        // principio, comparado con el parámetro `sign` en mayúsculas.
        //
        // IMPORTANTE: sin secreto configurado se rechaza el webhook
        // (fail-closed), igual que Amazon y Shein. Antes este método
        // devolvía `true` siempre y aceptaba payloads sin firmar.
        $secret = config('services.affiliate.aliexpress_webhook_secret');

        if (! $secret) {
            Log::warning('AliExpress webhook secret no configurado; webhook rechazado.');
            return false;
        }

        $params = $request->all();
        $sign = $params['sign'] ?? null;
        unset($params['sign']);

        if (! is_string($sign) || $sign === '') {
            return false;
        }

        ksort($params);

        $base = '';
        foreach ($params as $k => $v) {
            // Los arrays no participan en la firma de AliExpress.
            if (is_array($v)) {
                continue;
            }
            $base .= $k . $v;
        }

        $expected = strtoupper(md5($secret . $base));

        return $this->signatureEquals($expected, $sign);
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

    protected function tagParam(): string
    {
        return 'aff_fcid';
    }

    protected function subParam(): string
    {
        return 'sub_id';
    }

    protected function extraParams(): array
    {
        return ['aff_platform' => 'api-new-link-generate'];
    }
}
