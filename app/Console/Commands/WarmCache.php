<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\NewsItem;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Console\Command;

/**
 * cache:warm — pre-calienta la caché Redis de los hot paths más golpeados
 * (home y catálogo página 1) tras un despliegue o reinicio, para que el
 * primer visitante no pague el cold-start.
 *
 * Uso:
 *   php artisan cache:warm
 */
class WarmCache extends Command
{
    protected $signature = 'cache:warm';

    protected $description = 'Pre-calienta la caché Redis de home + catálogo.';

    public function handle(CacheService $cache): int
    {
        $this->info('Calentando caché Redis...');

        // Home: featured, novedades, flash, dropshipping, noticias.
        $cache->rememberVersioned('home', 'v1', function () {
            return [
                'featured'     => Product::active()->featured()->with('category')->latest()->take(8)->get(),
                'newArrivals'  => Product::active()->with('category')->latest()->take(8)->get(),
                'categories'   => Category::where('is_active', true)->orderBy('name')->take(10)->get(),
                'flash'        => Product::active()->onSale()->with('category')->latest()->take(6)->get(),
                'dropshipping' => Product::active()->where('is_dropshipping', true)->with('category')->latest()->take(8)->get(),
                'newsCarousel' => NewsItem::active()->featured()->latest('published_at')->take((int) config('news.home_featured', 4))->get(),
                'newsFeed'     => NewsItem::active()->latest('published_at')->take((int) config('news.home_limit', 8))->get(),
            ];
        });

        // Catálogo página 1 (orden por defecto, sin filtros): el caso más común.
        $cache->rememberVersioned('catalog', 'list:'.md5(serialize(['', '', 'newest', 1])), function () {
            return [
                'products'   => Product::active()->with('category')->latest()->paginate(12),
                'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            ];
        });

        // Noticias página 1.
        $cache->rememberVersioned('news', 'list:'.md5(serialize([1])), function () {
            return NewsItem::active()->latest('published_at')->paginate(12);
        });

        $this->info('Caché caliente: home, catálogo (página 1) y noticias.');

        return self::SUCCESS;
    }
}
