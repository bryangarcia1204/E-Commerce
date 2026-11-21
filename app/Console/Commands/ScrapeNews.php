<?php

namespace App\Console\Commands;

use App\Models\NewsItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * scrape:news — alimenta la tabla news_items desde feeds RSS curados de
 * moda y ofertas. Diseñado para correr en cron (ej. cada hora) y poblar
 * la sección "Novedades / Promociones" de la home.
 *
 * Consideraciones:
 *   - Sólo lee feeds RSS/Atom públicos (formato estructurado), no hace
 *     scraping HTML de páginas arbitrarias (frágil y a menudo contra ToS).
 *   - Dedup por source_url y por slug.
 *   - Timeout y manejo de errores por feed: un feed caído no aborta el
 *     resto.
 *   - Cachea 1h para no bombardear el feed si el cron corre más a menudo.
 *
 * Uso:
 *   php artisan scrape:news
 *   php artisan scrape:news --force   # ignora la cache
 *
 * La lista de feeds vive en config/news.php (publicable).
 */
class ScrapeNews extends Command
{
    protected $signature = 'scrape:news {--force : Ignora la cache de feeds}';

    protected $description = 'Importa novedades y promociones desde feeds RSS curados de moda.';

    public function handle(): int
    {
        $feeds = config('news.feeds', []);
        if (empty($feeds)) {
            $this->warn('No hay feeds configurados en config/news.php. Saliendo.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $imported = 0;
        $skipped = 0;

        foreach ($feeds as $feed) {
            try {
                $items = $this->fetchFeed($feed, $force);
            } catch (\Throwable $e) {
                Log::warning('scrape:news: feed fallido ['.$feed['url'].']: '.$e->getMessage());
                $this->warn('Feed fallido: '.$feed['name'].' — '.$e->getMessage());
                continue;
            }

            foreach ($items as $entry) {
                $exists = NewsItem::where('source_url', $entry['source_url'] ?? '')
                    ->orWhere('title', $entry['title'] ?? '')
                    ->exists();

                if ($exists || empty($entry['title']) || empty($entry['source_url'])) {
                    $skipped++;
                    continue;
                }

                NewsItem::create([
                    'type'         => $feed['type'] ?? 'news',
                    'title'        => Str::limit($entry['title'], 190),
                    'excerpt'      => isset($entry['excerpt']) ? Str::limit(strip_tags($entry['excerpt']), 280) : null,
                    'cover_image'  => $entry['cover_image'] ?? null,
                    'source_url'   => $entry['source_url'],
                    'source_name'  => $feed['name'] ?? null,
                    'is_featured'  => false,
                    'is_active'    => true,
                    'published_at' => $entry['published_at'] ?? now(),
                ]);
                $imported++;
            }

            $this->info('Feed OK: '.$feed['name'].' ('.count($items).' items)');
        }

        $this->info("Import: {$imported} nuevas, {$skipped} duplicadas.");
        Log::info('scrape:news completado', ['imported' => $imported, 'skipped' => $skipped]);

        // Invalida la caché Redis del feed de novedades y de la home
        // (extracto de noticias) si se importaron items nuevos.
        if ($imported > 0) {
            app(\App\Services\CacheService::class)->bumpVersions(['news', 'home']);
        }

        return self::SUCCESS;
    }

    /**
     * Descarga y parsea un feed RSS/Atom. Cachea el resultado 1h por URL.
     *
     * @return array<int, array{title: string, excerpt?: string, source_url: string, cover_image?: ?string, published_at?: ?string}>
     */
    private function fetchFeed(array $feed, bool $force): array
    {
        $cacheKey = 'scrape:news:'.md5($feed['url']);

        $xml = $force ? null : \Illuminate\Support\Facades\Cache::get($cacheKey);

        if ($xml === null) {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'MODA-news-bot/1.0 (+https://moda.local)'])
                ->get($feed['url']);

            if (! $response->successful()) {
                throw new \RuntimeException('HTTP '.$response->status());
            }
            $xml = $response->body();
            \Illuminate\Support\Facades\Cache::put($cacheKey, $xml, now()->addHour());
        }

        $parsed = @simplexml_load_string($xml);
        if ($parsed === false) {
            throw new \RuntimeException('XML inválido.');
        }

        $items = [];

        // RSS 2.0: <channel><item>…</item></channel>
        // Atom: <entry>…</entry>
        $entries = isset($parsed->channel->item) ? $parsed->channel->item : ($parsed->entry ?? []);

        foreach ($entries as $e) {
            $link = (string) ($e->link ?? '');
            // Atom: link puede ser atributo href
            if (! $link && isset($e->link['href'])) {
                $link = (string) $e->link['href'];
            }

            $items[] = [
                'title'        => trim((string) ($e->title ?? '')),
                'excerpt'      => trim((string) ($e->description ?? $e->summary ?? '')),
                'source_url'   => $link,
                'cover_image'  => $this->extractImage($e),
                'published_at' => isset($e->pubDate) ? date('Y-m-d H:i:s', strtotime((string) $e->pubDate)) : (now()->toDateTimeString()),
            ];
        }

        return $items;
    }

    private function extractImage(\SimpleXMLElement $e): ?string
    {
        // enclosure (RSS)
        if (isset($e->enclosure['url'])) {
            return (string) $e->enclosure['url'];
        }
        // media:content
        $media = $e->children('media', 'http://search.yahoo.com/mrss/');
        if (isset($media->content['url'])) {
            return (string) $media->content['url'];
        }
        // primera <img src> en la descripción
        $desc = (string) ($e->description ?? $e->summary ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)/i', $desc, $m)) {
            return $m[1];
        }

        return null;
    }
}
