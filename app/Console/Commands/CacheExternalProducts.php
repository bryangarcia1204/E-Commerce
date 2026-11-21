<?php

namespace App\Console\Commands;

use App\Models\ExternalProductCache;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * cache:external-products — refresca el caché del catálogo externo
 * (external_product_cache) para que sobreviva a reinicios y caídas del
 * servidor o de la API de la tienda externa.
 *
 * En pre-producción no hay API real de las tiendas, así que siembra el caché
 * desde los productos dropshipping ya dados de alta en nuestra BD. En
 * producción, sustituye el bloque de seeding por una llamada HTTP a la API
 * de cada tienda (Shein Affiliates API, AliExpress Portals API, Amazon
 * Product Advertising API) y cachea el snapshot devuelto.
 *
 * Uso:
 *   php artisan cache:external-products
 */
class CacheExternalProducts extends Command
{
    protected $signature = 'cache:external-products';

    protected $description = 'Refresca el caché del catálogo externo (dropshipping) en BD.';

    public function handle(): int
    {
        $ttl = now()->addMinutes((int) config('dropship.cache_ttl_minutes', 60));
        $refreshed = 0;

        // Productos dropshipping con tienda externa definida.
        $products = Product::where('is_dropshipping', true)
            ->whereNotNull('external_store')
            ->whereNotNull('external_product_id')
            ->get();

        foreach ($products as $product) {
            $payload = [
                'name'         => $product->name,
                'price'        => (float) $product->price,
                'discount_price' => $product->discount_price ? (float) $product->discount_price : null,
                'image'        => $product->image,
                'external_url' => $product->external_url,
                'is_active'    => $product->is_active,
                'cached_from'  => 'local_seed',
            ];

            ExternalProductCache::updateOrCreate(
                [
                    'external_store' => $product->external_store,
                    'external_product_id' => $product->external_product_id,
                ],
                [
                    'payload' => $payload,
                    'cached_at' => now(),
                    'expires_at' => $ttl,
                ]
            );
            $refreshed++;
        }

        $this->info("Caché externo refrescado: {$refreshed} productos. Válido hasta {$ttl->format('H:i')}.");
        Log::info('cache:external-products completado', ['refreshed' => $refreshed]);

        return self::SUCCESS;
    }
}
