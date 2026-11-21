<?php

namespace App\Http\Controllers;

use App\Models\NewsItem;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Páginas estáticas/institucionales enlazadas desde el footer y otros
 * puntos de la UI. El contenido es Blade (editable por el equipo); no
 * requiere BD salvo la página de inicio de novedades, que alimenta el
 * home con NewsItem.
 */
class PageController extends Controller
{
    public function __construct(private CacheService $cache) {}

    public function shipping(): View
    {
        return view('pages.envios');
    }

    public function returns(): View
    {
        return view('pages.devoluciones');
    }

    public function privacy(): View
    {
        return view('pages.privacidad');
    }

    public function terms(): View
    {
        return view('pages.terminos');
    }

    public function about(): View
    {
        return view('pages.nosotros');
    }

    /**
     * Página de novedades/tendencias completa (la home muestra un extracto).
     * Cacheamos el HTML renderizado del parcial (NO el Paginator Eloquent,
     * que provocaba "incomplete object" al deserializar). Invalida scrape:news.
     * Sin formularios CSRF aquí → no hace falta substituteCsrf.
     */
    public function news(Request $request): View
    {
        $suffix = 'list:' . md5(serialize([(int) $request->get('page', 1)]));

        $content = $this->cache->rememberVersioned('news', $suffix, function () {
            return \Illuminate\Support\Facades\View::make('partials.pages.novedades-content', [
                'items' => NewsItem::active()->latest('published_at')->paginate(12),
            ])->render();
        });

        return view('pages.novedades', ['content' => $content]);
    }
}
