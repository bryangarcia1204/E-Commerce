<?php

/**
 * Configuración del motor dropshipping + afiliación.
 */
return [

    // Tiempo de validez del caché de catálogo externo (external_product_cache).
    // Mientras no caduque, el catálogo se sirve desde BD aunque la API de la
    // tienda externa esté caída o el servidor se haya reiniciado.
    'cache_ttl_minutes' => (int) env('DROPSHIP_CACHE_TTL', 60),

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
