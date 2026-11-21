<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade índices que faltaban en `orders` para acelerar las consultas
 * frecuentes del panel de administración y del historial de cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status');
            $table->index('user_id');
            $table->index('created_at');
            $table->index('customer_email');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['customer_email']);
        });
    }
};
