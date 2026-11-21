<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Items de "novedades / promociones / tendencias" que se muestran en la
 * home. Se pueblan vía:
 *   1. Seeder con contenido curado de ejemplo.
 *   2. Comando `scrape:news` que lee RSS feeds de moda/ofertas y los
 *      almacena aquí (con cache y respetando ToS de cada feed).
 *
 * Cada item puede enlazar a un producto interno (product_id) o a una URL
 * externa (source_url). cover_image es una URL absoluta (CDN del feed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            // trend | promo | news | lookbook
            $table->string('type', 20)->default('news');
            $table->string('title', 200);
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_image')->nullable();
            // Etiquetas para filtrar (json array de strings).
            $table->json('tags')->nullable();
            // Enlace al producto interno destacado (opcional).
            $table->foreignId('product_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            // URL de origen (RSS item link) o promoción externa.
            $table->string('source_url')->nullable();
            $table->string('source_name', 80)->nullable();
            // Destacado para el carrusel principal de la home.
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            // Fecha de publicación (puede diferir de created_at).
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'is_featured', 'published_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
