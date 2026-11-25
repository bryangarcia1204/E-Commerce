<?php

namespace Database\Seeders;

use App\Models\Ad;
use Illuminate\Database\Seeder;

/**
 * Anuncios de ejemplo con banners SVG locales (public/img/ads/).
 * Los clicks/impresiones se miden vía /ads/click/{id} y /ads/impression/{id}.
 */
class AdSeeder extends Seeder
{
    public function run(): void
    {
        $ads = [
            [
                'name'       => 'Banner envío gratis (home, entre secciones)',
                'provider'   => 'internal',
                'position'   => Ad::POSITION_HOME_MID,
                'type'       => Ad::TYPE_IMAGE,
                'image_url'  => asset('img/ads/banner-1.svg'),
                'target_url' => route('products.index'),
                'is_active'  => true,
                'sort_order' => 0,
            ],
            [
                'name'       => 'Banner ofertas (home, bajo ofertas flash)',
                'provider'   => 'internal',
                'position'   => Ad::POSITION_HOME_FLASH,
                'type'       => Ad::TYPE_IMAGE,
                'image_url'  => asset('img/ads/banner-2.svg'),
                'target_url' => route('products.index', ['sort' => 'price_asc']),
                'is_active'  => true,
                'sort_order' => 0,
            ],
            [
                'name'       => 'Banner tiendas asociadas (catálogo)',
                'provider'   => 'internal',
                'position'   => Ad::POSITION_CATALOG_TOP,
                'type'       => Ad::TYPE_IMAGE,
                'image_url'  => asset('img/ads/banner-3.svg'),
                'target_url' => route('home') . '#tiendas',
                'is_active'  => true,
                'sort_order' => 0,
            ],
            [
                'name'       => 'Banner envío gratis (barra del footer, global)',
                'provider'   => 'internal',
                'position'   => Ad::POSITION_FOOTER_BAR,
                'type'       => Ad::TYPE_IMAGE,
                'image_url'  => asset('img/ads/banner-1.svg'),
                'target_url' => route('pages.envios'),
                'is_active'  => true,
                'sort_order' => 0,
            ],
        ];

        foreach ($ads as $ad) {
            Ad::firstOrCreate(
                ['name' => $ad['name']],
                $ad
            );
        }
    }
}
