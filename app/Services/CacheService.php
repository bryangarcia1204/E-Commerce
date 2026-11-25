<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CacheService — capa de caché Redis para las "peticiones muy seguidas".
 *
 * Diseño:
 *
 *  1) Fallback graceful: si Redis no está disponible (caída, reinicio,
 *     timeout), NO se lanza una excepción a la request. Se loguea un
 *     warning y se ejecuta el closure directamente (servicio desde BD).
 *     Un hot path nunca debe tirar la página por un fallo de caché.
 *
 *  2) Invalidación por versión (sin KEYS scan): cada namespace (catalog,
 *     home, news, dashboard) tiene un contador de versión en Redis. Las
 *     claves cacheadas incluyen esa versión: `catalog:v3:<suffix>`. Al
 *     mutar datos, bumpVersion() incrementa el contador y todas las
 *     claves anteriores quedan huérfanas y expiran por TTL. Evita el
 *     costoso `KEYS prefix*` / `SCAN` que bloquea Redis en producción.
 *
 *  3) Store configurable: por defecto el store de CACHE_STORE (redis en
 *     prod). Se puede forzar otro para tests.
 */
class CacheService
{
    /** TTL por defecto por namespace (segundos). Resuelven a config/dropship.php
     *  con estos valores como fallback si la config no está publicada. */
    private const DEFAULT_TTL = [
        'catalog'   => 600,   // 10 min — listados y detalle de producto
        'home'      => 600,   // 10 min — bloques de la home
        'news'      => 900,   // 15 min — feed de novedades
        'dashboard' => 60,    // 1 min  — agregados admin
        'cart'      => 30,    // 30 s   — conteo del carrito por usuario
        'ads'       => 300,   // 5 min  — anuncio activo por posición
    ];

    public function __construct(private ?string $store = null) {}

    /**
     * Recuerda un valor bajo una clave versionada del namespace.
     *
     * @param string  $namespace catalog|home|news|dashboard
     * @param string  $suffix    parte variable de la clave (slug, hash de filtros...)
     * @param \Closure $callback fuente del dato si hay miss
     */
    public function rememberVersioned(string $namespace, string $suffix, \Closure $callback): mixed
    {
        $key = $this->versionedKey($namespace, $suffix);
        $ttl = (int) (config("dropship.hot_path_ttl.{$namespace}") ?? self::DEFAULT_TTL[$namespace] ?? 300);

        try {
            return Cache::store($this->store())->remember($key, $ttl, $callback);
        } catch (\Throwable $e) {
            Log::warning("Cache miss (Redis unavailable) namespace={$namespace} key={$key}: {$e->getMessage()}");
            return $callback();
        }
    }

    /**
     * Sustituye el placeholder __CSRF_TOKEN__ por el token real de la sesión.
     *
     * Los parciales cacheados como HTML (home, catálogo, detalle) contienen
     * formularios add-to-cart con <input name="_token" value="__CSRF_TOKEN__">
     * en lugar de @csrf, para que el blob cacheado NUNCA guarde un token de
     * sesión (lo que provocaría 419 a otros usuarios). El controlador llama a
     * este método tras leer la caché (tanto en hit como en miss) para inyectar
     * el token correcto de la request actual.
     */
    public function substituteCsrf(string $html): string
    {
        return str_replace('__CSRF_TOKEN__', csrf_token(), $html);
    }

    /**
     * Recuerda un valor bajo una clave plana (sin versión). Útil para
     * claves por-usuario (p.ej. conteo del carrito) que se invalidan
     * individualmente con forget().
     */
    public function remember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        $ttl ??= 30;

        try {
            return Cache::store($this->store())->remember($key, $ttl, $callback);
        } catch (\Throwable $e) {
            Log::warning("Cache miss (Redis unavailable) key={$key}: {$e->getMessage()}");
            return $callback();
        }
    }

    public function forget(string $key): void
    {
        try {
            Cache::store($this->store())->forget($key);
        } catch (\Throwable $e) {
            // no-op: si Redis está caído, la clave ya no existe.
        }
    }

    /**
     * Invalida todo un namespace (catalog, home, news...) incrementando
     * su versión. Las claves huérfanas expiran por TTL.
     */
    public function bumpVersion(string $namespace): void
    {
        try {
            Cache::store($this->store())->increment("{$namespace}:version");
        } catch (\Throwable $e) {
            Log::warning("bumpVersion falló namespace={$namespace}: {$e->getMessage()}");
        }
    }

    /**
     * Invalida varios namespaces a la vez (p.ej. guardar un producto
     * afecta a catálogo y a la home).
     */
    public function bumpVersions(array $namespaces): void
    {
        foreach ($namespaces as $ns) {
            $this->bumpVersion($ns);
        }
    }

    private function versionedKey(string $namespace, string $suffix): string
    {
        $version = 1;
        try {
            $version = (int) (Cache::store($this->store())->get("{$namespace}:version") ?: 1);
        } catch (\Throwable $e) {
            // Redis caído: versión 1, clave normal; el remember() caerá
            // también en graceful y servirá desde BD.
        }

        // Esquema 's3': las tarjetas/detalle ahora llevan botón wishlist
        // (data-wishlist). s2 cacheaba HTML sin el botón; sin bump, tras
        // desplegar se serviría el HTML viejo (sin data-wishlist) y el JS no
        // encontraría corazones hasta expirar el TTL. Al pasar a :s3: las
        // entradas s2 quedan huérfanas y expiran → migración self-healing.
        // Subir este valor si vuelve a cambiar el formato del valor cacheado.
        return "{$namespace}:v{$version}:s3:" . preg_replace('/[^a-zA-Z0-9_.\-:]/', '', $suffix);
    }

    private function store(): ?string
    {
        return $this->store ?? config('cache.default');
    }
}
