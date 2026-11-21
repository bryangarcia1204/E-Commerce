<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Añade cabeceras HTTP de seguridad recomendadas para producción.
 * Especialmente importante cuando la app se sirve detrás de un proxy / CDN.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // No tocar respuestas de streaming ni archivos binarios grandes.
        if ($response->isSuccessful() || $response->isClientError() || $response->isServerError()) {
            // Anti-clickjacking
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

            // Anti-MIME sniffing
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            // Referrer (no filtrar: solo origen para evitar fugas en enlaces salientes)
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

            // Permisos de la plataforma web (desactiva cámara, micro, geo, etc.)
            $response->headers->set(
                'Permissions-Policy',
                'camera=(), microphone=(), geolocation=(), payment=(self "https:")'
            );

            // HSTS sólo cuando servimos por HTTPS y APP_ENV=production.
            if ($request->isSecure() && app()->environment('production')) {
                $response->headers->set(
                    'Strict-Transport-Security',
                    'max-age=31536000; includeSubDomains; preload'
                );
            }
        }

        return $response;
    }
}
