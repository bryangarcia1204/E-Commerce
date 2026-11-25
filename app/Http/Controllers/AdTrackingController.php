<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Services\AdsService;
use Illuminate\Http\Request;

/**
 * Rutas públicas de tracking de anuncios.
 *
 *   GET /ads/click/{ad}       → clicks++ + redirect al destino
 *   GET /ads/impression/{ad}  → impressions++ + píxel GIF 1x1
 *
 * Las impresiones se miden con un píxel (GET) en lugar de al renderizar
 * porque el HTML de home/catálogo se cachea: la URL del píxel es
 * determinística por anuncio y funciona igual en la caché.
 *
 * Seguridad del redirect: destino interno (path relativo) o URL http(s)
 * externa válida; se rechazan esquemas peligrosos (javascript:, data:...).
 */
class AdTrackingController extends Controller
{
    private const PIXEL_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __construct(private AdsService $ads) {}

    public function click(Request $request, Ad $ad)
    {
        if (! $ad->is_active) {
            abort(404);
        }

        $this->ads->trackClick($ad);

        $target = $ad->target_url;

        if (! $target) {
            return redirect()->route('home');
        }

        // Destino interno: path relativo.
        if (str_starts_with($target, '/') && ! str_starts_with($target, '//')) {
            return redirect()->to($target);
        }

        // Destino externo: sólo http/https (anti javascript:/data:).
        if (filter_var($target, FILTER_VALIDATE_URL)
            && in_array(parse_url($target, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return redirect()->away($target);
        }

        // Target inválido: contamos el click pero volvemos a un destino seguro.
        return redirect()->route('home');
    }

    public function impression(Request $request, Ad $ad)
    {
        if (! $ad->is_active) {
            abort(404);
        }

        $this->ads->trackImpression($ad);

        return response(base64_decode(self::PIXEL_GIF), 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }
}
