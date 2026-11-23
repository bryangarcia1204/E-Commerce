<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AffiliateRedirectController extends Controller
{
    /**
     * Whitelist de hosts permitidos para evitar open-redirect.
     * Añade aquí cada dominio de proveedor que uses.
     */
    public const ALLOWED_HOSTS = [
        'www.aliexpress.com',
        'es.aliexpress.com',
        'aliexpress.com',
        'www.amazon.es',
        'www.amazon.com',
        'amazon.es',
        'amazon.com',
        'www.shein.com',
        'es.shein.com',
        'shein.com',
        'www.ebay.com',
        'www.ebay.es',
        'ebay.com',
        'ebay.es',
    ];

    public function redirect(Request $request, Product $product)
    {
        // 1) Producto activo y realmente de afiliación/dropshipping con URL externa
        if (
            ! $product->is_active
            || ! $product->is_dropshipping
            || ! $product->external_url
        ) {
            abort(404, 'Este producto no tiene tienda externa asociada.');
        }

        // 2) La URL debe ser válida y apuntar a un host permitido
        $subId = (string) Str::uuid();
        $request->attributes->set('affiliate_sub_id', $subId);
        $target = $product->affiliate_url;

        if (! filter_var($target, FILTER_VALIDATE_URL)) {
            abort(404, 'URL de destino inválida.');
        }

        $host = parse_url($target, PHP_URL_HOST);

        if (! in_array($host, self::ALLOWED_HOSTS, true)) {
            abort(403, 'Destino no autorizado.');
        }

        // 3) Rate limit: máximo 30 redirecciones por minuto por IP
        $key = 'affiliate-redirect:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Inténtalo más tarde.');
        }
        RateLimiter::hit($key, 60);

        // 4) Registrar el clic (sin bloquear la redirección si falla)
        try {
            AffiliateClick::create([
                'user_id'           => $request->user()?->id,
                'product_id'        => $product->id,
                'ip_address'        => $request->ip(),
                'user_agent'        => Str::limit((string) $request->userAgent(), 500),
                'session_id'        => $request->session()->getId(),
                'referer'           => Str::limit((string) $request->headers->get('referer'), 500),
                'sub_id'            => $subId,
                'network'           => $product->external_store,
                'commission_amount' => $product->unit_commission ?? 0,
                'status'            => 'pending',
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        // 5) Redirigir sin cachear para que el clic se cuente siempre
        return redirect()->away($target, 302)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
        ]);
        
    }
}