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

    // --- Precios (markup) -------------------------------------------------
    // Cómo se calcula el precio de venta (price) al importar un producto con
    // cost_price conocido (sourcing por proveedor). El margen bruto por venta
    // = price - cost_price (lo guarda Order::margin_total en el checkout).
    // Cifras realistas de la industria: margen neto 10-30% (Printful), los
    // dropshippers con más éxito 20-30%/venta (BlueCard); low-ticket hasta 50%.
    // Un factor 1.40 ≈ +40% sobre coste (margen bruto ~29%), dentro del rango
    // sano. Ajustar por nicho: moda low-ticket puede subir a 1.6-2.0.
    'pricing' => [
        'markup_factor' => (float) env('DROPSHIP_MARKUP_FACTOR', 1.40),
        'min_price'     => (float) env('DROPSHIP_MIN_PRICE', 1.00), // no bajar de 1 €
        'round_to'      => (float) env('DROPSHIP_ROUND_TO', 0.05),  // redondeo "psicológico" a 5 céntimos
    ],

    // --- Importación de feed de productos ---------------------------------
    // NO se scrapean las tiendas directamente (violación de ToS + copyright
    // de imágenes). Los programas de afiliados y los proveedores dropshipping
    // publican feeds de catálogo (JSON/CSV) pensados para que los socios los
    // importen: AliExpress Portals (hasta 9% comisión, 90% en Hot Products),
    // Amazon Product Advertising API (hasta 10%), Awin/CJ (CSV/XML), Spocket/
    // Modalyst (JSON). products:import lee uno de esos feeds y aplica markup.
    'import' => [
        'default_affiliate_tag'   => env('DROPSHIP_AFFILIATE_TAG', 'moda-aff'),
        'default_commission_rate' => (float) env('DROPSHIP_DEFAULT_COMMISSION', 6.00), // %
        'default_commission_type' => env('DROPSHIP_COMMISSION_TYPE', 'percent'), // percent|fixed
        'default_stock'           => (int) env('DROPSHIP_DEFAULT_STOCK', 999),
        // Timeout HTTP al descargar un feed remoto.
        'http_timeout'            => (int) env('DROPSHIP_IMPORT_TIMEOUT', 30),
    ],
];
