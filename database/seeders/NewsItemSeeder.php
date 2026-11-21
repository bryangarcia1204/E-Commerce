<?php

namespace Database\Seeders;

use App\Models\NewsItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Puebla la sección "Novedades / Tendencias" con contenido curado de
 * ejemplo para pre-producción. En producción se complementa con el
 * comando scrape:news (RSS feeds reales).
 */
class NewsItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'type' => 'trend',
                'title' => 'Tendencia OTOÑO/INVIERNO 2026: el regreso del cargo',
                'excerpt' => 'Los pantalones cargo vuelven con fuerza, reinventados en paños premium y siluetas oversize. Te contamos cómo llevarlos.',
                'source_name' => 'MODA Editorial',
                'tags' => ['tendencia', 'cargo', 'otono'],
                'is_featured' => true,
            ],
            [
                'type' => 'promo',
                'title' => 'PROMO: -40% en zapatos de temporada',
                'excerpt' => 'Solo hasta fin de semana. Botines, sneakers y sandalias rebajados en productos propios con stock limitado.',
                'source_name' => 'MODA',
                'tags' => ['promo', 'zapatos', 'rebajas'],
                'is_featured' => true,
            ],
            [
                'type' => 'trend',
                'title' => 'Minimalismo cálido: la paleta tierra que domina esta temporada',
                'excerpt' => 'Beige, terracota y marrón chocolate. La paleta que unifica los looks de otoño tanto en tiendas propias como asociadas.',
                'source_name' => 'MODA Editorial',
                'tags' => ['tendencia', 'color', 'minimal'],
                'is_featured' => true,
            ],
            [
                'type' => 'news',
                'title' => 'Nueva colección de tiendas asociadas: +200 productos Shein y Zara',
                'excerpt' => 'Hemos ampliado el catálogo dropshipping con nuevas referencias de Shein y Zara, todas canalizadas con enlace de afiliado.',
                'source_name' => 'MODA',
                'tags' => ['novedad', 'dropshipping', 'shein'],
                'is_featured' => true,
            ],
            [
                'type' => 'lookbook',
                'title' => 'Lookbook: 5 outfits para la oficina con 1 sola chaqueta oversize',
                'excerpt' => 'Versatilidad máxima. Una blazer oversize combina del lunes al viernes con estos cinco conjuntos.',
                'source_name' => 'MODA Editorial',
                'tags' => ['lookbook', 'blazer', 'oficina'],
                'is_featured' => false,
            ],
            [
                'type' => 'promo',
                'title' => 'Cashback en tu billetera: 5% al comprar en tiendas asociadas',
                'excerpt' => 'Por cada compra completada en Shein, AliExpress o Amazon a través de nuestro enlace, recibes un 5% en crédito de billetera.',
                'source_name' => 'MODA',
                'tags' => ['promo', 'cashback', 'billetera'],
                'is_featured' => false,
            ],
            [
                'type' => 'trend',
                'title' => 'Accesorios statement: el cinturón como protagonista',
                'excerpt' => 'Los cinturones de cuero ancho con hebilla dorada son el detalle que eleva cualquier look simple.',
                'source_name' => 'MODA Editorial',
                'tags' => ['tendencia', 'accesorios'],
                'is_featured' => false,
            ],
            [
                'type' => 'news',
                'title' => 'Sostenibilidad: cómo reutilizamos tus devoluciones',
                'excerpt' => 'Nueva política de segunda oportunidad: los productos propios devueltos en buen estado se reponen con etiqueta "rewear".',
                'source_name' => 'MODA',
                'tags' => ['sostenibilidad', 'devoluciones'],
                'is_featured' => false,
            ],
        ];

        foreach ($items as $i => $item) {
            NewsItem::create([
                'type'         => $item['type'],
                'title'        => $item['title'],
                'slug'         => Str::slug($item['title']).'-'.$i,
                'excerpt'      => $item['excerpt'],
                'body'         => null,
                'cover_image'  => null, // placeholder local
                'tags'         => $item['tags'],
                'source_url'   => null,
                'source_name'  => $item['source_name'],
                'is_featured'  => $item['is_featured'],
                'is_active'    => true,
                'published_at' => now()->subDays(count($items) - $i),
            ]);
        }
    }
}
