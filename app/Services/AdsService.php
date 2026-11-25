<?php

namespace App\Services;

use App\Models\Ad;

/**
 * AdsService — resolución de anuncios para cada posición del frontend.
 *
 * El anuncio activo de cada posición se cachea 5 min (namespace 'ads',
 * invalidable por versión con bumpVersion('ads') al mutar desde admin).
 * Las impresiones y clicks NO se cuentan al renderizar (el HTML de home/
 * catálogo se cachea), sino desde las rutas de tracking:
 *   GET /ads/impression/{ad} → píxel 1x1 + impressions++
 *   GET /ads/click/{ad}      → clicks++ + redirect al target
 */
class AdsService
{
    public function __construct(private CacheService $cache) {}

    /**
     * Anuncio activo para una posición (o null si no hay/ads desactivados).
     * Master switch: config('services.ads.enabled') (ADS_ENABLED).
     */
    public function forPosition(string $position): ?Ad
    {
        if (! config('services.ads.enabled', false)) {
            return null;
        }

        // Centinela `false` para cache negativa: si la posición no tiene
        // anuncio, no queremos re-consultar la BD en cada request (el
        // remember trataría null como miss). flush() invalida por versión.
        $ad = $this->cache->rememberVersioned(
            'ads',
            'pos:' . $position,
            fn () => Ad::serving($position)->first() ?? false
        );

        return $ad ?: null;
    }

    /** Registra una impresión (desde el píxel de tracking). */
    public function trackImpression(Ad $ad): void
    {
        Ad::whereKey($ad->id)->where('is_active', true)->increment('impressions');
    }

    /** Registra un click (desde la ruta /ads/click/{ad}). */
    public function trackClick(Ad $ad): void
    {
        Ad::whereKey($ad->id)->where('is_active', true)->increment('clicks');
    }

    /** Invalida la caché de posiciones (al mutar desde el admin). */
    public function flush(): void
    {
        $this->cache->bumpVersion('ads');
    }
}
