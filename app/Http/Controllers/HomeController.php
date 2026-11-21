<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\NewsItem;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Support\Facades\View;

class HomeController extends Controller
{
    public function __construct(private CacheService $cache) {}

    public function index()
    {
        // La home es el hot path más golpeado. Cacheamos el HTML renderizado
        // del parcial (NO objetos Eloquent): serializar un string en caché es
        // trivial y evita el error "incomplete object" al deserializar
        // Collections, además de evitar que SerializesModels descarte las
        // relaciones eager-loaded (N+1). El fetch va dentro del closure →
        // sólo se ejecuta en miss. Invalida al mutar productos/noticias.
        $content = $this->cache->rememberVersioned('home', 'v1', function () {
            return View::make('partials.home.content', [
                'featured'    => Product::active()->featured()->with('category')->latest()->take(8)->get(),
                'newArrivals' => Product::active()->with('category')->latest()->take(8)->get(),
                'categories'  => Category::where('is_active', true)->orderBy('name')->take(10)->get(),
                'flash'       => Product::active()->onSale()->with('category')->latest()->take(6)->get(),
                'dropshipping'=> Product::active()->where('is_dropshipping', true)->with('category')->latest()->take(8)->get(),
                'newsCarousel'=> NewsItem::active()->featured()->latest('published_at')->take((int) config('news.home_featured', 4))->get(),
                'newsFeed'    => NewsItem::active()->latest('published_at')->take((int) config('news.home_limit', 8))->get(),
            ])->render();
        });

        // El blob cacheado lleva placeholders __CSRF_TOKEN__ en los formularios
        // add-to-cart de las tarjetas; los sustituimos por el token real de la
        // sesión de ESTA request (evita 419 entre usuarios).
        $content = $this->cache->substituteCsrf($content);

        // El layout (cabecera, carrito, auth) se renderiza fuera de la caché:
        // sigue siendo dinámico por usuario.
        return view('home', ['content' => $content]);
    }
}
