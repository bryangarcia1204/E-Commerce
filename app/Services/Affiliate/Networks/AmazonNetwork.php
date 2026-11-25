<?php

namespace App\Services\Affiliate\Networks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AmazonNetwork extends AbstractAffiliateNetwork
{
    public function name(): string
    {
        return 'Amazon';
    }

    public function slug(): string
    {
        return 'amazon';
    }

    public function verify(Request $request): bool
    {
        // Amazon envía SNS con firma. En producción, valida SNS con el
        // certificado y verifica `TopicArn` contra el tuyo. Simplificación
        // para dev/demo: token secreto compartido en cabecera.
        $secret = config('services.affiliate.amazon_webhook_secret');

        if (! $secret) {
            Log::warning('Amazon webhook secret no configurado.');
            return false;
        }

        return hash_equals($secret, (string) $request->header('X-Amz-Sns-Token'));
    }

    public function extractSubId(Request $request): ?string
    {
        return data_get($request->all(), 'sub_id')
            ?? data_get($request->all(), 'ascsubtag')
            ?? data_get($request->all(), 'tracking_id');
    }

    public function extractCommission(Request $request): float
    {
        return (float) (data_get($request->all(), 'earnings')
            ?? data_get($request->all(), 'commission')
            ?? 0);
    }

    public function extractExternalOrderId(Request $request): ?string
    {
        return data_get($request->all(), 'order_id')
            ?? data_get($request->all(), 'OrderId');
    }

    protected function tagParam(): string
    {
        return 'tag';
    }

    protected function subParam(): string
    {
        // Amazon Associates usa ascsubtag para el sub-tracking del click.
        return 'ascsubtag';
    }
}
