<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger de la billetera virtual. Registro inmutable (append-only) de
 * movimientos: recargas promocionales, cashback de comisiones de
 * afiliación, reembolsos y consumos en checkout. El saldo actual
 * (users.wallet_balance) se reconstruye sumando este ledger, lo que
 * permite auditoría y detección de manipulaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // credit | debit
            $table->string('direction', 10);
            // promo_credit | cashback | refund | purchase | adjustment
            $table->string('type', 30);
            $table->decimal('amount', 10, 2);
            // Saldo resultante tras el movimiento (snapshot para auditoría).
            $table->decimal('balance_after', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->nullableMorphs('source'); // source_type=Order, source_id=...
            $table->string('description', 200)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
