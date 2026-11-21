<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métodos de pago guardados por el usuario. NUNCA almacenamos PAN completos
 * ni CVV. Guardamos sólo una referencia tokenizada (últimos 4 dígitos para
 * mostrar en UI + un identificador opaco del procesador de pagos, tipo
 * Stripe payment_method_id). Para PayPal/billetera guardamos sólo el email
 * vinculado. En pre-producción sin pasarela real, el "token" es un
 * placeholder marcado explícitamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // card | paypal | wallet
            $table->string('type', 20);
            // Marca emisora para tarjetas (visa/mastercard/amex) o proveedor.
            $table->string('provider', 40)->nullable();
            // Últimos 4 dígitos para mostrar en UI (nunca el PAN completo).
            $table->string('last4', 4)->nullable();
            // Identificador opaco devuelto por la pasarela. En demo, un UUID.
            $table->string('token', 120);
            // Email vinculado (PayPal) o alias de billetera.
            $table->string('email', 150)->nullable();
            // Fecha de caducidad (sólo mes/año, nunca día). Nullable para
            // métodos sin caducidad (PayPal, wallet).
            $table->string('expires_at', 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
