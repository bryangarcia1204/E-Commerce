<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carrito persistente en BD para usuarios autenticados.
 *
 * Antes el carrito vivía sólo en sesión y se perdía al cerrar sesión o al
 * reiniciar/caer el servidor. Con esta tabla, el carrito de un usuario
 * autenticado sobrevive entre reinicios y sesiones (como un "cache durable").
 * Los invitados siguen usando la sesión (no tienen user_id).
 *
 * Relación: un usuario tiene un único carrito (one-to-one efectivo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            // Un usuario = un carrito. unique para garantizar 1:1.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Precio snapshot en el momento de añadir (informativo; sync()
            // refresca desde el producto para no facturar precios obsoletos).
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            // Un producto sólo aparece una vez por carrito (se acumula qty).
            $table->unique(['cart_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
