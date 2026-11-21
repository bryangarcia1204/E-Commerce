<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Habilita SoftDeletes en productos.
 *
 * Razón: el controlador admin hacía DELETE físico, pero order_items tiene
 * FK restrictOnDelete contra products. Borrar un producto con pedidos
 * asociados rompía la FK. Con SoftDeletes, el producto se oculta del
 * catálogo (lo filtra automáticamente el scope global) sin perder el
 * histórico de pedidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
