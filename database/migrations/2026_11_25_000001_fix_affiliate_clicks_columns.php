<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX del sistema de registro de clics de afiliación.
 *
 * La migración original (`create_affiliate_clicks_table`) definió
 * `redirect_url` TEXT **NOT NULL** sin valor por defecto, pero
 * `AffiliateRedirectController` nunca la rellenaba → el INSERT fallaba
 * con "NOT NULL constraint failed: affiliate_clicks.redirect_url" y la
 * excepción se tragaba con report(), de modo que el clic NUNCA quedaba
 * registrado (y las comisiones/webhooks no tenían sub_id al que vincularse).
 *
 * El controlador ahora rellena TODAS las columnas de auditoría
 * (redirect_url, external_store, sale_price, estimated_commission) y el
 * modelo las tiene en $fillable. Aquí además:
 *   - `redirect_url` pasa a nullable (defensivo: para filas/entornos
 *     antiguos y para que nada bloquee el INSERT de un clic).
 *   - índice (network, created_at) para la conciliación/filtros por red.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_clicks', function (Blueprint $table) {
            if (Schema::hasColumn('affiliate_clicks', 'redirect_url')) {
                $table->text('redirect_url')->nullable()->change();
            }
        });

        $indexNames = collect(Schema::getIndexes('affiliate_clicks'))->pluck('name');

        Schema::table('affiliate_clicks', function (Blueprint $table) use ($indexNames) {
            if (! $indexNames->contains('affiliate_clicks_network_created_at_index')) {
                $table->index(['network', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        $indexNames = collect(Schema::getIndexes('affiliate_clicks'))->pluck('name');

        Schema::table('affiliate_clicks', function (Blueprint $table) use ($indexNames) {
            if ($indexNames->contains('affiliate_clicks_network_created_at_index')) {
                $table->dropIndex(['network', 'created_at']);
            }
        });

        Schema::table('affiliate_clicks', function (Blueprint $table) {
            if (Schema::hasColumn('affiliate_clicks', 'redirect_url')) {
                $table->text('redirect_url')->nullable(false)->change();
            }
        });
    }
};
