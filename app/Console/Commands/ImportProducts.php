<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * products:import — importa un feed de productos de un proveedor/afiliado y
 * aplica el markup de dropshipping.
 *
 * POR QUÉ FEED Y NO SCRAPING DIRECTO DE TIENDAS
 * ----------------------------------------------
 * Scrapear Shein/Zara/AliExpress/Amazon (sus páginas de producto) viola sus
 * ToS y reutiliza imágenes con copyright. NO es la vía legal ni la que usan
 * las tiendas dropshipping reales. Lo que sí usan son los **feeds de
 * catálogo** que publican los programas de afiliados y los proveedores para
 * que sus socios los importen:
 *
 *   - AliExpress Portals Affiliate   → feed/API de productos (hasta 9% comisión)
 *   - Amazon Product Advertising API → hasta 10% comisión
 *   - Awin / CJ Affiliate / Rakuten  → feeds CSV/XML de productos
 *   - Spocket / Modalyst / DSers    → JSON de catálogo de proveedores
 *
 * Este comando lee uno de esos feeds (JSON o CSV, local o remoto) y, por cada
 * producto:
 *   - lo resuelve por (external_store, external_product_id) → create/update,
 *   - calcula el precio de venta con markup (cost_price × factor) si el feed
 *     trae coste, o usa el precio del feed si sólo trae precio (modo afiliado),
 *   - rellena affiliate_tag, commission_rate/type, external_url, imagen...
 *   - lo marca is_dropshipping=true para que el checkout calcule
 *     margin_total (price - cost_price) y commission_total.
 *
 * Uso:
 *   php artisan products:import --path=database/feeds/sample_products.json
 *   php artisan products:import --url=https://feed.proveedor.com/catalog.json
 *   php artisan products:import --path=feed.csv --format=csv --dry
 */
class ImportProducts extends Command
{
    protected $signature = 'products:import
        {--path= : Ruta local al feed (JSON o CSV)}
        {--url= : URL remota del feed}
        {--format=auto : auto|json|csv}
        {--dry : Sólo simular, no escribir}
        {--tag= : Sobreescribir affiliate_tag para todo el feed}';

    protected $description = 'Importa un feed de productos (JSON/CSV) y aplica markup dropshipping.';

    public function handle(CacheService $cache): int
    {
        $path = $this->option('path');
        $url  = $this->option('url');

        if (! $path && ! $url) {
            $this->error('Indica --path=<fichero> o --url=<feed remoto>.');
            return self::FAILURE;
        }

        $raw = $path ? $this->readLocal($path) : $this->readRemote($url);
        if ($raw === null) {
            return self::FAILURE;
        }

        $format = $this->option('format') === 'auto'
            ? ($path && str_ends_with(strtolower($path), '.csv') ? 'csv' : 'json')
            : $this->option('format');

        $items = $format === 'csv' ? $this->parseCsv($raw) : $this->parseJson($raw);
        if ($items === null) {
            $this->error('No se pudo parsear el feed.');
            return self::FAILURE;
        }

        $this->info(sprintf('Feed cargado: %d productos (formato %s).', count($items), $format));

        $cfg = config('dropship');
        $pricing = $cfg['pricing'];
        $importCfg = $cfg['import'];
        $overrideTag = $this->option('tag');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $row = $this->normalize($item, $importCfg, $overrideTag);

            // Validación mínima.
            if (! $row['external_store'] || ! $row['external_product_id'] || ! $row['name']) {
                $skipped++;
                $this->warn("  ↳ Saltado (falta external_store/external_product_id/name): " . ($row['name'] ?: '(sin nombre)'));
                continue;
            }

            // Precio: modo markup (cost_price × factor) o modo afiliado (price del feed).
            $price = $this->computePrice($row, $pricing);
            if ($price === null) {
                $skipped++;
                $this->warn("  ↳ Saltado (sin cost_price ni price): {$row['name']}");
                continue;
            }

            if ($this->option('dry')) {
                $this->line("  [DRY] {$row['external_store']}:#{$row['external_product_id']} «{$row['name']}» → {$price} €");
                continue;
            }

            $category = $this->resolveCategory($row['category_slug'] ?? null);
            $slug = $this->uniqueSlug($row['name']);

            $product = Product::where('external_store', $row['external_store'])
                ->where('external_product_id', $row['external_product_id'])
                ->first();

            $fields = [
                'category_id'       => $category->id,
                'name'              => $row['name'],
                'slug'              => $slug,
                'description'       => $row['description'] ?? null,
                'price'             => $price,
                'discount_price'    => $row['discount_price'] ?? null,
                'stock'             => $row['stock'] ?? $importCfg['default_stock'],
                'image'             => $row['image'] ?? null,
                'is_active'         => true,
                'is_featured'       => ! empty($row['is_featured']),
                'external_store'    => $row['external_store'],
                'external_product_id' => $row['external_product_id'],
                'external_url'      => $row['external_url'] ?? null,
                'affiliate_tag'     => $row['affiliate_tag'],
                'cost_price'        => $row['cost_price'] ?? null,
                'commission_rate'   => $row['commission_rate'],
                'commission_type'   => $row['commission_type'],
                'is_dropshipping'   => true,
            ];

            if ($product) {
                // No pisamos el slug existente (SEO/links estables) ni el stock
                // si el feed no lo trae (lo gestiona la tienda externa).
                unset($fields['slug']);
                if ($fields['stock'] === null) {
                    unset($fields['stock']);
                }
                $product->update($fields);
                $updated++;
            } else {
                Product::create($fields);
                $created++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Importación %s: %d creados, %d actualizados, %d saltados.',
            $this->option('dry') ? '(DRY)' : 'completa',
            $created,
            $updated,
            $skipped
        ));

        // Refresca caché externo + invalida hot paths si hubo cambios.
        if (! $this->option('dry') && ($created > 0 || $updated > 0)) {
            $cache->bumpVersions(['catalog', 'home']);
            $this->line('Caché invalidada (catalog, home). Ejecuta cache:external-products para refrescar el snapshot externo.');
            Log::info('products:import completado', compact('created', 'updated', 'skipped'));
        }

        return self::SUCCESS;
    }

    private function readLocal(string $path): ?string
    {
        if (! is_file($path)) {
            $this->error("No existe el fichero: {$path}");
            return null;
        }
        return (string) file_get_contents($path);
    }

    private function readRemote(string $url): ?string
    {
        $timeout = (int) (config('dropship.import.http_timeout') ?? 30);
        try {
            $res = Http::timeout($timeout)->get($url);
            if (! $res->ok()) {
                $this->error("HTTP {$res->status()} al descargar {$url}");
                return null;
            }
            return $res->body();
        } catch (\Throwable $e) {
            $this->error("No se pudo descargar el feed: {$e->getMessage()}");
            return null;
        }
    }

    private function parseJson(string $raw): ?array
    {
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }
        // Acepta {"products":[...]} o un array plano.
        if (isset($data['products']) && is_array($data['products'])) {
            return $data['products'];
        }
        return array_values($data);
    }

    private function parseCsv(string $raw): ?array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($raw));
        if (! $lines) {
            return [];
        }
        $header = str_getcsv(array_shift($lines));
        $items = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line);
            if (count($row) < count($header)) {
                continue;
            }
            $items[] = array_combine($header, array_slice($row, 0, count($header)));
        }
        return $items;
    }

    /**
     * Normaliza un item del feed a los campos del modelo Product, aplicando
     * defaults de config('dropship.import'). Acepta sinónimos comunes en
     * feeds reales (title/name, cost/cost_price, image/image_url...).
     */
    private function normalize(array $item, array $importCfg, ?string $overrideTag): array
    {
        $get = static fn ($keys) => $item[$keys[0]] ?? $item[$keys[1]] ?? $item[$keys[2] ?? 'x'] ?? null;

        $cost = $get(['cost_price', 'cost', 'wholesale_price']);
        $price = $get(['price', 'sale_price', 'retail_price']);

        return [
            'external_store'      => strtolower((string) $get(['external_store', 'store', 'source'])),
            'external_product_id' => (string) $get(['external_product_id', 'external_id', 'id', 'sku']),
            'external_url'        => $get(['external_url', 'url', 'link', 'product_url']),
            'name'                => $get(['name', 'title', 'product_name']),
            'description'         => $get(['description', 'desc', 'summary']),
            'image'               => $get(['image', 'image_url', 'img', 'thumbnail']),
            'cost_price'          => $cost !== null ? (float) $cost : null,
            'price'               => $price !== null ? (float) $price : null,
            'discount_price'      => ($get(['discount_price', 'sale_price_discount']) !== null) ? (float) $get(['discount_price', 'sale_price_discount']) : null,
            'category_slug'       => $get(['category_slug', 'category', 'cat']) ? Str::slug((string) $get(['category_slug', 'category', 'cat'])) : null,
            'stock'               => isset($item['stock']) ? (int) $item['stock'] : null,
            'is_featured'         => ! empty($item['is_featured']),
            'affiliate_tag'       => $overrideTag ?: $get(['affiliate_tag', 'affid', 'tag']) ?: $importCfg['default_affiliate_tag'],
            'commission_rate'     => isset($item['commission_rate']) ? (float) $item['commission_rate'] : $importCfg['default_commission_rate'],
            'commission_type'     => $get(['commission_type']) ?: $importCfg['default_commission_type'],
        ];
    }

    /**
     * Precio de venta.
     *  - Modo MARKUP (hay cost_price): price = cost × factor, con mínimo y
     *    redondeo "psicológico" (a 5 céntimos). Es el precio que el cliente
     *    paga A NOSOTROS → lo controlamos.
     *  - Modo AFILIADO (sólo price del feed): se usa el precio de la tienda
     *    tal cual (es lo que el cliente paga ALLÍ; no lo controlamos y no
     *    debemos maquillarlo con redondeo que descuadre del real).
     */
    private function computePrice(array $row, array $pricing): ?float
    {
        $factor = (float) $pricing['markup_factor'];
        $min    = (float) $pricing['min_price'];
        $round  = (float) $pricing['round_to'];

        if ($row['cost_price'] !== null && $row['cost_price'] > 0) {
            $price = $row['cost_price'] * $factor;
            $price = max($price, $min);
            if ($round > 0) {
                $price = round($price / $round) * $round;
            }
        } elseif ($row['price'] !== null && $row['price'] > 0) {
            $price = $row['price'];
        } else {
            return null;
        }

        return round($price, 2);
    }

    private function resolveCategory(?string $slug): Category
    {
        if (! $slug) {
            $slug = 'sin-categoria';
        }
        return Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucwords(str_replace('-', ' ', $slug)), 'is_active' => true]
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
