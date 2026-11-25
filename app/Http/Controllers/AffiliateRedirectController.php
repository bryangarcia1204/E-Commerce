<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\Product;
use App\Services\Affiliate\AffiliateNetworkManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AffiliateRedirectController extends Controller
{
    /**
     * Whitelist de hosts permitidos para evitar open-redirect. Fuente:
     * config('dropship.allowed_hosts') (única fuente de verdad, compartida
     * con el importer) + variantes regionales es.* ampliadas aquí.
     */
    public static function allowedHosts(): array
    {
        $configured = (array) config('dropship.allowed_hosts', []);

        $regional = [
            'es.aliexpress.com', 'es.shein.com', 'www.amazon.es', 'amazon.es',
            'www.ebay.com', 'www.ebay.es', 'ebay.com', 'ebay.es',
        ];

        return array_values(array_unique(array_merge($configured, $regional)));
    }

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
        $target = $product->external_url;

        if (! filter_var($target, FILTER_VALIDATE_URL)) {
            abort(404, 'URL de destino inválida.');
        }

        $host = parse_url($target, PHP_URL_HOST);

        if (! in_array($host, self::allowedHosts(), true)) {
            abort(403, 'Destino no autorizado.');
        }

        // 3) Rate limit: máximo 30 redirecciones por minuto por IP
        $key = 'affiliate-redirect:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Demasiadas solicitudes. Inténtalo más tarde.');
        }
        RateLimiter::hit($key, 60);

        // 4) Construir la URL de afiliado (tag + sub_id) vía la red. El
        //    sub_id se inyecta en los attributes de la request para que
        //    Product::affiliate_url lo utilice, y se normaliza la red
        //    (external_store se guarda en minúsculas: 'shein', 'amazon'...).
        $manager = app(AffiliateNetworkManager::class);
        $network = $manager->resolve($product->external_store);
        $subId   = (string) Str::uuid();

        $request->attributes->set('affiliate_sub_id', $subId);
        $target = $product->affiliate_url;

        // 5) Registrar el clic. FIX: antes el INSERT fallaba en silencio
        //    (redirect_url era NOT NULL y nunca se rellenaba, y faltaban
        //    external_store/sale_price/estimated_commission en el modelo),
        //    así que los clics NO quedaban registrados. Ahora se rellenan
        //    todas las columnas de auditoría y el fallo se reporta sin
        //    bloquear la redirección.
        try {
            AffiliateClick::create([
                'user_id'             => $request->user()?->id,
                'product_id'          => $product->id,
                'ip_address'          => $request->ip(),
                'user_agent'          => Str::limit((string) $request->userAgent(), 500),
                'session_id'          => Str::limit((string) $request->session()->getId(), 100),
                'referer'             => Str::limit((string) $request->headers->get('referer'), 500),
                'sub_id'              => $subId,
                'network'             => $network?->slug(),
                'external_store'      => AffiliateNetworkManager::normalize($product->external_store),
                'redirect_url'        => Str::limit($target, 500),
                'sale_price'          => $product->current_price,
                'estimated_commission' => round($product->unit_commission + $product->unit_profit, 2),
                'commission_amount'   => $product->unit_commission ?: 0,
                'status'              => 'pending',
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        // 6) Redirigir sin cachear para que el clic se cuente siempre
        return redirect()->away($target, 302)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
        ]);
    }
}
