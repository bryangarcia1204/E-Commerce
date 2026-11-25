<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de anuncios (ads) gestionable desde el panel admin.
 *
 * Cada fila es un anuncio para una POSICIÓN del frontend (home-mid,
 * catalog-top, product-mid, cart-top, footer-bar...). El componente blade
 * <x-ad-slot position="..."/> resuelve el anuncio activo vía AdsService
 * (cacheado) y lo renderiza:
 *   - type 'image': banner con imagen + link, con tracking de impresiones
 *     (píxel 1x1) y clicks (ruta /ads/click/{ad}).
 *   - type 'html': snippet crudo del proveedor externo (Adsterra, Monetag…)
 *     — sólo lo puede crear un admin.
 *
 * Las métricas (impressions/clicks) se incrementan desde las rutas públicas
 * de tracking, no al renderizar, para que funcionen incluso en HTML cacheado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            // Nombre interno para el admin ("Banner envío gratis - navidad").
            $table->string('name', 120);
            // Proveedor: internal | adsterra | monetag | ...
            $table->string('provider', 60)->default('internal');
            // Posición del layout donde se muestra (índice).
            $table->string('position', 40)->index();
            // image | html
            $table->string('type', 20)->default('image');
            // Snippet del proveedor (sólo type=html; entrada de admin).
            $table->text('html')->nullable();
            $table->text('image_url')->nullable();
            $table->text('target_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            // Ventana de vigencia (opcional): fuera de ella no se muestra.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // Métricas de tracking (rutas /ads/*).
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            // Orden dentro de la posición (menor = primero).
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
