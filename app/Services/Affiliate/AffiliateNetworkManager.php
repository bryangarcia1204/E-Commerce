<?php

namespace App\Services\Affiliate;

use App\Services\Affiliate\Contracts\AffiliateNetwork;
use App\Services\Affiliate\Networks\AliExpressNetwork;
use App\Services\Affiliate\Networks\AmazonNetwork;
use App\Services\Affiliate\Networks\GenericNetwork;
use App\Services\Affiliate\Networks\SheinNetwork;
use Illuminate\Support\Str;

/**
 * Registro central de redes de afiliación.
 *
 * PROBLEMA que resuelve: los slugs de tienda se guardan en minúsculas en
 * `products.external_store` (seeder + feed importer), pero el código
 * comparaba contra 'Amazon'/'AliExpress'/'Shein' capitalizados y cada pieza
 * (URL builder, filtros de conciliación, webhook) usaba su propia lista.
 * Ahora TODO pasa por aquí: una única fuente de verdad con normalización.
 */
class AffiliateNetworkManager
{
    /** Mapa slug → clase. Registro explícito, sin auto-discovery mágica. */
    private const NETWORKS = [
        'amazon'     => AmazonNetwork::class,
        'aliexpress' => AliExpressNetwork::class,
        'shein'      => SheinNetwork::class,
    ];

    /** Instancias cacheadas por slug (se piden varias veces por request). */
    private array $instances = [];

    /**
     * Normaliza cualquier valor de tienda/red al slug interno en minúsculas:
     * 'Amazon', 'AMAZON', ' Amazon ' → 'amazon'. Devuelve null para vacío.
     */
    public static function normalize(?string $store): ?string
    {
        $slug = Str::slug(trim((string) $store), '');

        // Str::slug elimina espacios; 'Ali Express' → 'aliexpress'.
        return $slug !== '' ? $slug : null;
    }

    /**
     * Resuelve la red por slug/tienda (case-insensitive). Devuelve la red
     * dedicada si existe (amazon/aliexpress/shein) y, para cualquier otra
     * tienda con programa de afiliados (zara, asos...), la GenericNetwork
     * para que el click SIEMPRE quede rastreable con sub_id.
     */
    public function resolve(?string $store): ?AffiliateNetwork
    {
        $slug = $store === null ? null : self::normalize($store);

        if ($slug === null) {
            return null;
        }

        if (isset(self::NETWORKS[$slug])) {
            return $this->instance($slug, self::NETWORKS[$slug]);
        }

        return $this->instance('generic', GenericNetwork::class);
    }

    /** ¿Tiene la tienda una red con integración dedicada? */
    public function isKnown(?string $store): bool
    {
        $slug = self::normalize($store);

        return $slug !== null && isset(self::NETWORKS[$slug]);
    }

    /** Instancia por slug exacto (para el webhook: la ruta ya viene normalizada). */
    public function bySlug(string $slug): ?AffiliateNetwork
    {
        $slug = self::normalize($slug);

        if ($slug === null) {
            return null;
        }

        if (isset(self::NETWORKS[$slug])) {
            return $this->instance($slug, self::NETWORKS[$slug]);
        }

        return null;
    }

    /**
     * Opciones para filtros/UI: slug → nombre legible. Incluye 'generic'
     * para ver los clics de tiendas sin integración dedicada.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach (self::NETWORKS as $slug => $class) {
            $options[$slug] = $this->instance($slug, $class)->name();
        }

        $options['generic'] = $this->instance('generic', GenericNetwork::class)->name();

        return $options;
    }

    private function instance(string $slug, string $class): AffiliateNetwork
    {
        return $this->instances[$slug] ??= app($class);
    }
}
