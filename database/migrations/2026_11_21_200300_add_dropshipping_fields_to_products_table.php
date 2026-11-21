<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo de dropshipping + afiliación.
 *
 * Cada producto puede estar enlazado a una tienda real (Shein, AliExpress,
 * Amazon, Zara...) de la que el promotor se surte. El flujo es:
 *   - El usuario navega el catálogo con precios ya marcados (price).
 *   - Al comprar, se le redirige a la tienda original con un tag de
 *     afiliado (external_url + affiliate_tag).
 *   - El promotor (nosotros) gana el margen (price - cost_price) y, si la
 *     tienda tiene programa de afiliados, una comisión extra calculada
 *     sobre el precio de venta (commission_rate).
 *
 * Para productos propios (no dropshipping), is_dropshipping=false y los
 * campos externos quedan a NULL; se comportan como antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // shein | aliexpress | amazon | zara | custom | null (propio)
            $table->string('external_store', 20)->nullable()->after('image');
            // ID del producto en la tienda externa (para analytics/reconciliación).
            $table->string('external_product_id', 80)->nullable()->after('external_store');
            // URL canónica del producto en la tienda externa (sin tag).
            $table->string('external_url')->nullable()->after('external_product_id');
            // Tag/ID de afiliado a añadir a la URL al redirigir.
            $table->string('affiliate_tag', 60)->nullable()->after('external_url');
            // Coste al que el promotor adquiere el producto (para calcular margen).
            $table->decimal('cost_price', 10, 2)->nullable()->after('affiliate_tag');
            // Comisión que la tienda paga al promotor por canalizar la venta.
            $table->decimal('commission_rate', 5, 2)->default(0)->after('cost_price');
            // percent | fixed — tipo de cálculo de la comisión.
            $table->string('commission_type', 10)->default('percent')->after('commission_rate');
            // Marca el producto como dropshipping (redirige a tienda externa).
            $table->boolean('is_dropshipping')->default(false)->after('commission_type');

            $table->index(['is_dropshipping', 'is_active']);
            $table->index('external_store');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_dropshipping', 'is_active']);
            $table->dropIndex(['external_store']);
            $table->dropColumn([
                'external_store',
                'external_product_id',
                'external_url',
                'affiliate_tag',
                'cost_price',
                'commission_rate',
                'commission_type',
                'is_dropshipping',
            ]);
        });
    }
};
