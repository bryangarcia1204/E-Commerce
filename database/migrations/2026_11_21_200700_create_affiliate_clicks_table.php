<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log de clics salientes hacia tiendas externas (dropshipping/afiliación).
 * Cada vez que un usuario pulsa "Comprar en Shein" (etc.) se registra una
 * fila para auditoría de comisiones y detección de fraude/click-spam.
 * Se vincula al usuario si estaba autenticado y al producto canalizado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();
            // shein | aliexpress | amazon | zara | custom
            $table->string('external_store', 20)->nullable();
            // URL completa (con tag de afiliado) a la que se redirigió.
            $table->text('redirect_url');
            // Precio de venta y comisión estimada en el momento del clic.
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('estimated_commission', 10, 2)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            // Identificador de sesión para agrupar clics del mismo visitante.
            $table->string('session_id', 40)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_clicks');
    }
};
