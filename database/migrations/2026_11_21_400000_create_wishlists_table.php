<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wishlists — lista de favoritos por usuario (persistente en BD).
 *
 * Una fila por par (user_id, product_id) con UNIQUE → añadir dos veces el
 * mismo producto no duplica. Sobrevive a reinicios/caídas (igual que el
 * carrito DB). El borrado del usuario o del producto elimina en cascada.
 *
 * Aislamiento: cada fila pertenece a su user_id; el WishlistController
 * filtra siempre por auth()->id() y un usuario nunca ve/edita los favoritos
 * de otro (no hace falta policy: el scope es siempre el del usuario en sesión).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Un usuario no puede tener el mismo producto dos veces en favoritos.
            $table->unique(['user_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
