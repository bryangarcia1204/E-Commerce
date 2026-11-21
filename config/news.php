<?php

/**
 * Configuración del importador de novedades (comando scrape:news).
 *
 * La lista de feeds es curada: sólo se incluyen feeds RSS/Atom públicos
 * de medios de moda y secciones de ofertas de marcas. NO se hace scraping
 * HTML de páginas arbitrarias (frágil y a menudo contra los ToS del site).
 *
 * Para añadir un feed, incluye url + nombre + type (trend|promo|news|lookbook).
 * El comando cachea cada feed 1h y dedup por source_url/título.
 */
return [

    'feeds' => [
        [
            'name' => 'Vogue Runway',
            'url'  => 'https://www.vogue.com/feed/rss/runway.xml',
            'type' => 'trend',
        ],
        [
            'name' => 'Elle Fashion',
            'url'  => 'https://www.elle.com/fashion/rss/',
            'type' => 'trend',
        ],
        [
            'name' => 'Highsnobiety Style',
            'url'  => 'https://www.highsnobiety.com/category/style/feed/',
            'type' => 'trend',
        ],
        [
            'name' => 'Hypebeast Fashion',
            'url'  => 'https://hypebeast.com/fashion/feed',
            'type' => 'trend',
        ],
        [
            'name' => 'Business of Fashion',
            'url'  => 'https://www.businessoffashion.com/feed',
            'type' => 'news',
        ],
    ],

    // Cuántos items mostrar en la home (carousel + grid).
    'home_limit' => 8,

    // Cuántos destacados van al carrusel principal.
    'home_featured' => 4,
];
