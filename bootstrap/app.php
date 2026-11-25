<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Cabeceras de seguridad para producción (HSTS, X-Frame-Options,
        // X-Content-Type-Options, Referrer-Policy, Permissions-Policy).
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Confianza de proxies (balanceadores / CDN / terminación TLS).
        $middleware->trustProxies(at: '*');

        // Alias de middleware de la aplicación.
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Excluir webhooks de CSRF (los firma el proveedor con su propio
        // mecanismo: HMAC compartida, no tokens de sesión).
        $middleware->preventRequestForgery(except: [
            'webhooks/affiliate/*',
            'webhooks/stripe',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
