<?php

namespace App\Services\Affiliate\Networks;

use App\Services\Affiliate\Contracts\AffiliateNetwork;
use Illuminate\Http\Request;

/**
 * Base común de las redes de afiliación.
 *
 * Centraliza la construcción de la URL de afiliado (merge de query params
 * sobre la URL base, sin duplicar "?" ni sobrescribir parámetros existentes)
 * para que cada red sólo declare sus nombres de parámetro.
 */
abstract class AbstractAffiliateNetwork implements AffiliateNetwork
{
    /** Nombre del query param donde va el tag/ID de afiliado. */
    abstract protected function tagParam(): string;

    /** Nombre del query param donde va el sub_id de tracking (click). */
    protected function subParam(): string
    {
        return 'sub_id';
    }

    /** Parámetros extra fijos de la red (ej. aff_platform en AliExpress). */
    protected function extraParams(): array
    {
        return [];
    }

    public function buildAffiliateUrl(string $baseUrl, ?string $tag, ?string $subId): string
    {
        $tag   = trim((string) $tag);
        $subId = trim((string) $subId);

        $params = $this->extraParams();

        if ($tag !== '') {
            $params[$this->tagParam()] = $tag;
        }

        if ($subId !== '') {
            $params[$this->subParam()] = $subId;
        }

        if ($params === []) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query($params);
    }

    /**
     * Utilidad para las verificaciones de firma: normaliza a mayúsculas
     * y compara en tiempo constante.
     */
    protected function signatureEquals(string $expected, ?string $received): bool
    {
        return is_string($received)
            && $received !== ''
            && hash_equals(strtoupper($expected), strtoupper($received));
    }

    abstract public function name(): string;
    abstract public function slug(): string;
    abstract public function verify(Request $request): bool;
    abstract public function extractSubId(Request $request): ?string;
    abstract public function extractCommission(Request $request): float;
    abstract public function extractExternalOrderId(Request $request): ?string;
}
