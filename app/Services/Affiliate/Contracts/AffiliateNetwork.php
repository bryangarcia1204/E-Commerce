<?php

namespace App\Services\Affiliate\Contracts;

use Illuminate\Http\Request;

interface AffiliateNetwork
{
    /** Identificador de la red (ej. 'Amazon', 'AliExpress'). */
    public function name(): string;

    /** Verifica autenticidad del webhook. */
    public function verify(Request $request): bool;

    /** Extrae el sub_id (o click_id) del payload. */
    public function extractSubId(Request $request): ?string;

    /** Extrae el monto real de la comisión (en la moneda de la red). */
    public function extractCommission(Request $request): float;

    /** Extrae el identificador de la orden externa. */
    public function extractExternalOrderId(Request $request): ?string;
}