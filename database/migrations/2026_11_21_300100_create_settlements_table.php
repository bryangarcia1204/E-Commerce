<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libro de cuentas pendientes (liquidaciones dropshipping).
 *
 * Cada pedido genera asientos financieros que reflejan:
 *   - receivable_from_customer: lo que el cliente nos debe (productos propios
 *     + envío) hasta que se confirma el pago. "Por cobrar".
 *   - commission_owed_by_store: comisión que la tienda externa nos debe por
 *     canalizar una venta dropshipping. "Por cobrar (comisión)".
 *   - payable_to_supplier: lo que debemos al proveedor de productos propios
 *     (si los adquirimos a un mayorista). "Por pagar".
 *
 * Estado: pending → settled (settled_at). Permite al admin ver el flujo de
 * caja pendiente sin tocar los secretos individuales del usuario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // nullOnDelete: al borrar la cuenta del usuario, el asiento
            // sobrevive vinculado al pedido (traza contable/fiscal), igual
            // que los pedidos. No es dato personal: es obligación financiera.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // receivable_from_customer | commission_owed_by_store | payable_to_supplier
            $table->string('type', 40);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            // pending | settled | disputed | written_off
            $table->string('status', 20)->default('pending');
            $table->string('description', 200)->nullable();
            $table->timestamp('settled_at')->nullable();
            // Referencia externa (ej. ID de la conversión de afiliado).
            $table->string('external_ref', 120)->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
