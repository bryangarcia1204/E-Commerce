<?php

namespace App\Services\Affiliate\Networks;

use App\Services\Affiliate\Contracts\AffiliateNetwork;
use Illuminate\Http\Request;

class SheinNetwork implements AffiliateNetwork
{
    public function name(): string
    {
        return 'Shein';
    }

    public function verify(Request $request): bool
    {
        // Shein usa HMAC-SHA256 sobre el body con secreto compartido.
        $secret = config('services.affiliate.shein_webhook_secret');
        if (! $secret) return false;

        $received = $request->header('X-Shein-Signature');
        if (! $received) return false;

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $received);
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
        return $request->input('order_id');
    }
}