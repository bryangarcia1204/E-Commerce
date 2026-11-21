<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\NewsItem;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * cache:warm — pre-calienta la caché Redis del hot path más golpeado (home)
 * tras un despliegue o reinicio, para que el primer visitante no pague el
 * cold-start.
 *
 * SÓLO calienta la home: catálogo y novedades contienen paginación (->links())
 * cuyas URLs dependen de la request HTTP real; pre-renderizarlos en consola
 * generaría URLs http://localhost rotas. Esos dos namespaces se calientan
 * solos en la primera request real (cache miss → render con request correcta
 * → cache). La home no usa request() ni paginación → segura en consola.
 *
 * El HTML cacheado lleva placeholders __CSRF_TOKEN__ en los formularios
 * add-to-cart; el HomeController los sustituye por csrf_token() en cada
 * request.
 *
 * Uso:
 *   php artisan cache:warm
 */
class WarmCache extends Command
{
    protected $signature = 'cache:warm';

    protected $description = 'Pre-calienta la caché Redis de la home.';

    public function handle(CacheService $cache): int
    {
        $this->info('Calentando caché Redis (home)...');

        // Home: featured, novedades, flash, dropshipping, noticias. Se renderiza
        // el parcial a HTML (no se cachean objetos Eloquent → evita
        // "incomplete object" al deserializar).
        $cache->rememberVersioned('home', 'v1', function () {
            return View::make('partials.home.content', [
                'featured'     => Product::active()->featured()->with('category')->latest()->take(8)->get(),
                'newArrivals'  => Product::active()->with('category')->latest()->take(8)->get(),
                'categories'   => Category::where('is_active', true)->orderBy('name')->take(10)->get(),
                'flash'        => Product::active()->onSale()->with('category')->latest()->take(6)->get(),
                'dropshipping' => Product::active()->where('is_dropshipping', true)->with('category')->latest()->take(8)->get(),
                'newsCarousel' => NewsItem::active()->featured()->latest('published_at')->take((int) config('news.home_featured', 4))->get(),
                'newsFeed'     => NewsItem::active()->latest('published_at')->take((int) config('news.home_limit', 8))->get(),
            ])->render();
        });

        $this->info('Caché caliente: home.');
        $this->line('  Catálogo y novedades se calientan en la 1ª request real (paginación depende de la URL).');

        return self::SUCCESS;
    }
}
