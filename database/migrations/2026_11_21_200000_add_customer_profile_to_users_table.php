<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil de cliente para acelerar el checkout y soportar la billetera
 * virtual. Estos campos son opcionales en el registro y editables desde
 * el perfil. Se tratan como PII y se anonimizan/eliminan al borrar la
 * cuenta (ver AccountDeletionService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Teléfono de contacto (opcional).
            $table->string('phone', 30)->nullable()->after('name');
            // Dirección de envío preferida para pre-poblar el checkout.
            $table->text('default_shipping_address')->nullable()->after('phone');
            // Método de pago preferido (slug: card / paypal / wallet / cod).
            // Es sólo una preferencia; las credenciales reales viven en
            // payment_methods. Nunca almacenamos números de tarjeta completos.
            $table->string('preferred_payment_method', 20)->default('card')->after('default_shipping_address');
            // Billetera virtual: saldo disponible del usuario.
            // Decimal(10,2) soporta hasta 9.999.999,99 — suficiente para
            // crédito promocional y reembolsos de pre-producción.
            $table->decimal('wallet_balance', 10, 2)->default(0)->after('preferred_payment_method');
            // Auditoría ligera: último login (útil para detectar cuentas
            // inactivas a purgar bajo GDPR).
            $table->timestamp('last_login_at')->nullable()->after('wallet_balance');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'default_shipping_address',
                'preferred_payment_method',
                'wallet_balance',
                'last_login_at',
            ]);
        });
    }
};
