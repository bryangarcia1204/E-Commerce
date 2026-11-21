<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caché del catálogo externo que sobrevive a reinicios y caídas del servidor.
 *
 * En dropshipping, los datos vivos de un producto (precio, stock, imagen,
 * disponibilidad) viven en la tienda externa (Shein, AliExpress...). Para no
 * depender de que esa API esté siempre disponible —y para que el catálogo se
 * siga mostrando tras un reinicio o caída— cacheamos una versión local por
 * par (store, external_product_id). El comando `cache:external-products`
 * refresca las entradas; las caducadas se ignoran y se re-cachean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_product_cache', function (Blueprint $table) {
            $table->id();
            $table->string('external_store', 20);
            $table->string('external_product_id', 80);
            // JSON con el snapshot: name, price, image, availability, url...
            $table->json('payload');
            $table->timestamp('cached_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['external_store', 'external_product_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_product_cache');
    }
};
