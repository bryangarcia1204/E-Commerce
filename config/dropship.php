<?php

/**
 * Configuración del motor dropshipping + afiliación.
 */
return [

    // Tiempo de validez del caché de catálogo externo (external_product_cache).
    // Mientras no caduque, el catálogo se sirve desde BD aunque la API de la
    // tienda externa esté caída o el servidor se haya reiniciado.
    'cache_ttl_minutes' => (int) env('DROPSHIP_CACHE_TTL', 60),

    // TTL (segundos) de los hot paths cacheados en Redis vía CacheService.
    // Invalidables por versión (bumpVersion) al mutar datos; estos TTL son
    // el techo máximo de "staleness" si la invalidación fallara.
    'hot_path_ttl' => [
        'catalog'   => (int) env('CACHE_TTL_CATALOG', 600),   // 10 min
        'home'      => (int) env('CACHE_TTL_HOME', 600),      // 10 min
        'news'      => (int) env('CACHE_TTL_NEWS', 900),      // 15 min
        'dashboard' => (int) env('CACHE_TTL_DASHBOARD', 60),  // 1 min
        'cart'      => (int) env('CACHE_TTL_CART', 30),       // 30 s
    ],

    // Lista blanca de dominios de tiendas externas permitidos para el
    // redirect de afiliado (anti open-redirect). Debe coincidir con los
    // hosts permitidos en AffiliateRedirectController.
    'allowed_hosts' => [
        'shein.com', 'www.shein.com',
        'aliexpress.com', 'www.aliexpress.com',
        'amazon.com', 'www.amazon.com',
        'zara.com', 'www.zara.com',
        'asos.com', 'www.asos.com',
    ],
];
