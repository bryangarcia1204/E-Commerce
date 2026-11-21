<?php

namespace App\Providers;

use App\Listeners\RecordSessionStart;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Gate de panel de administración (reutilizamos isAdmin() del modelo).
        Gate::define('access-admin-panel', fn ($user) => $user?->isAdmin() === true);

        // Listener de login: registra session_started_at + last_login_at
        // (para "sesión iniciada" / "tiempo transcurrido") y migra el carrito
        // de invitado al carrito persistente en BD del usuario.
        Event::listen(Login::class, RecordSessionStart::class);

        // Rate limiters con nombre usados por las rutas (throttle:web,
        // throttle:api). Laravel 11+ ya NO registra estos limiters por
        // defecto, así que sin este bloque cualquier ruta con
        // throttle:web / throttle:api lanza MissingRateLimiterException.
        // Clave por usuario autenticado y, en su defecto, por IP, para
        // no penalizar a varios clientes detrás de un mismo NAT.
        RateLimiter::for('web', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        // Limiter 'storefront' para los hot paths cacheados (home, catálogo,
        // detalle, novedades). Más generoso que 'web' (120/min) porque son
        // rutas de lectura pública muy frecuentes. Va respaldado por Redis
        // (CACHE_STORE=redis): el contador de peticiones vive en RAM, no en
        // la BD. Protege contra scrapers/bots que saturan el catálogo.
        RateLimiter::for('storefront', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        // En producción forzamos esquema HTTPS para todos los URL generados
        // (assets, enlaces de email, redirects). Evita mezcla HTTP/HTTPS
        // cuando el TLS se termina en un balanceador/CDN.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
