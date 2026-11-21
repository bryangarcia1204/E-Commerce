<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende orders para registrar el método de pago usado, el estado del
 * pago y la comisión/margen total ganado por el promotor en el pedido
 * (clave para el modelo de negocio dropshipping+afiliación).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // card | paypal | wallet | cod
            $table->string('payment_method', 20)->default('card')->after('shipping_address');
            // pending | paid | failed | refunded
            $table->string('payment_status', 20)->default('pending')->after('payment_method');
            // Margen total (precio venta - cost_price) sumado por item.
            $table->decimal('margin_total', 10, 2)->default(0)->after('total');
            // Comisión total cobrada al programa de afiliados.
            $table->decimal('commission_total', 10, 2)->default(0)->after('margin_total');
            // Referencias externas (order IDs devueltos por cada tienda) en JSON.
            $table->json('external_refs')->nullable()->after('commission_total');
            // ID del método de pago usado (si el usuario guardó uno).
            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->after('user_id');

            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'margin_total',
                'commission_total',
                'external_refs',
            ]);
        });
    }
};
