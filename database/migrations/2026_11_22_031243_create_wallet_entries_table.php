<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            // amount > 0 => crédito ; amount < 0 => débito. Nunca 0.
            $table->bigInteger('amount');
            $table->string('reason', 100);
            // Referencia polimórfica opcional: a un Order, a un AffiliateClick, a un Refund...
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            // Idempotencia: si dos procesos intentan aplicar la misma operación, solo uno gana
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_entries');
    }
};