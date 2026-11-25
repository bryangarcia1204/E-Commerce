<?php

namespace App\Services\Affiliate\Contracts;

use Illuminate\Http\Request;

interface AffiliateNetwork
{
    /** Identificador de la red (ej. 'Amazon'). */
    public function name(): string;

    /** Slug interno en minúsculas (ej. 'amazon'). Es la clave que se usa en
     *  external_store, affiliate_clicks.network y las rutas de webhook. */
    public function slug(): string;

    /** Verifica autenticidad del webhook. */
    public function verify(Request $request): bool;

    /** Extrae el sub_id (o click_id) del payload. */
    public function extractSubId(Request $request): ?string;

    /** Extrae el monto real de la comisión (en la moneda de la red). */
    public function extractCommission(Request $request): float;

    /** Extrae el identificador de la orden externa. */
    public function extractExternalOrderId(Request $request): ?string;

    /**
     * Construye la URL de afiliado final: external_url + tag + sub_id,
     * respetando los query params que ya traiga la URL base (nunca
     * duplica "?" ni machaca parámetros del proveedor).
     */
    public function buildAffiliateUrl(string $baseUrl, ?string $tag, ?string $subId): string;
}
