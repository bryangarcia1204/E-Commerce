<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\NewsItem;
use App\Models\Product;
use App\Services\CacheService;

class HomeController extends Controller
{
    public function __construct(private CacheService $cache) {}

    public function index()
    {
        // La home es el hot path más golpeado. Se cachea en Redis por bloque
        // (namespace 'home'); invalida al crear/editar/borrar productos o
        // noticias. Si Redis cae, CacheService sirve desde BD sin caché.
        $data = $this->cache->rememberVersioned('home', 'v1', function () {
            return [
                'featured'    => Product::active()->featured()->with('category')->latest()->take(8)->get(),
                'newArrivals' => Product::active()->with('category')->latest()->take(8)->get(),
                'categories'  => Category::where('is_active', true)->orderBy('name')->take(10)->get(),
                'flash'       => Product::active()->onSale()->with('category')->latest()->take(6)->get(),
                'dropshipping'=> Product::active()->where('is_dropshipping', true)->with('category')->latest()->take(8)->get(),
                'newsCarousel'=> NewsItem::active()->featured()->latest('published_at')->take((int) config('news.home_featured', 4))->get(),
                'newsFeed'    => NewsItem::active()->latest('published_at')->take((int) config('news.home_limit', 8))->get(),
            ];
        });

        return view('home', $data);
    }
}
