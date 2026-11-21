<?php

namespace App\Http\Controllers;

use App\Models\AffiliateClick;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * AffiliateRedirectController — punto único de salida hacia tiendas
 * externas (dropshipping/afiliación). Recibe el ID del producto,
 * construye la URL de afiliado, valida que el dominio destino esté en
 * la whitelist (anti open-redirect), registra el clic para auditoría de
 * comisiones y hace el 302.
 *
 * Centralizar la salida aquí permite:
 *   - Auditar todos los clics salientes.
 *   - Evitar que un atacante use el redirector para phising (whitelist).
 *   - Cambiar el esquema de tags/parámetros en un solo sitio.
 */
class AffiliateRedirectController extends Controller
{
    /**
     * Dominios de tiendas externas permitidos. Cualquier URL cuyo host
     * no esté exactamente en esta lista es rechazada con 403.
     */
    private const ALLOWED_HOSTS = [
        'shein.com', 'www.shein.com',
        'aliexpress.com', 'www.aliexpress.com',
        'amazon.com', 'www.amazon.com',
        'zara.com', 'www.zara.com',
        'asos.com', 'www.asos.com',
    ];

    public function redirect(Request $request, Product $product): RedirectResponse
    {
        // El producto debe ser dropshipping y tener URL externa.
        if (! $product->is_dropshipping || ! $product->external_url) {
            abort(404, 'Este producto no tiene tienda externa asociada.');
        }

        $affiliateUrl = $product->affiliate_url;

        // Validación anti open-redirect: el host debe estar en la whitelist.
        $host = parse_url($affiliateUrl, PHP_URL_HOST);
        if (! $host || ! in_array(strtolower($host), self::ALLOWED_HOSTS, true)) {
            abort(403, 'Dominio externo no permitido.');
        }

        // Log del clic para auditoría de comisiones y detección de fraude.
        AffiliateClick::create([
            'user_id' => $request->user()?->id,
            'product_id' => $product->id,
            'external_store' => $product->external_store,
            'redirect_url' => $affiliateUrl,
            'sale_price' => $product->current_price,
            'estimated_commission' => $product->unit_commission + $product->unit_profit,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'session_id' => $request->session()->getId(),
        ]);

        return redirect()->away($affiliateUrl, 302)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
