<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AccountDeletionService — borrado de cuenta conforme a RGPD (derecho al
 * olvido). Dado que el sistema maneja billeteras virtuales, métodos de
 * pago tokenizados y datos de envío, el borrado debe ser EXHAUSTIVO y en
 * cascada, dentro de una transacción DB.
 *
 * Política:
 *   - Datos financieros sensibles (tokens de pago, ledger de billetera,
 *     sesiones, tokens de reseteo de password, tokens Sanctum de API):
 *     borrado físico inmediato.
 *   - Pedidos: NO se borran (obligación legal de conservar registros
 *     contables/fiscales durante el plazo legal). Se ANONIMIZAN los PII
 *     (nombre, email, teléfono, dirección) pero se conservan totales,
 *     items y estados para auditoría.
 *   - Todo queda registrado en el log de auditoría con el ID del usuario,
 *     email (antes del borrado) y timestamp.
 */
class AccountDeletionService
{
    /**
     * Ejecuta el borrado completo de la cuenta del usuario.
     *
     * @return array{ok: bool, error?: string} resumen del resultado.
     */
    public function delete(User $user): array
    {
        $userId = $user->id;
        $userEmail = $user->email;
        $startedAt = now();

        try {
            DB::transaction(function () use ($user, $userId, $userEmail) {
                // 1) Pedidos: anonimizar PII, conservar totales/items.
                //    user_id queda NULL (FK nullOnDelete) al borrar el user.
                DB::table('orders')
                    ->where('user_id', $userId)
                    ->update([
                        'customer_name' => 'Usuario eliminado',
                        'customer_email' => null,
                        'customer_phone' => null,
                        'shipping_address' => 'Datos eliminados (RGPD)',
                        'payment_method_id' => null,
                    ]);

                // 2) Mensajes de contacto del usuario: borrar físico (PII).
                DB::table('contact_messages')->where('user_id', $userId)->delete();

                // 3) Clics de afiliación: borrar físico (vínculo con persona).
                DB::table('affiliate_clicks')->where('user_id', $userId)->delete();

                // 4) Sesiones activas: invalidar (logout forzado en todos los
                //    dispositivos, incluido el actual).
                DB::table('sessions')->where('user_id', $userId)->delete();

                // 5) Tokens de reseteo de password pendientes.
                DB::table('password_reset_tokens')->where('email', $userEmail)->delete();

                // 6) Tokens de API Sanctum (si la tabla existe; Sanctum la
                //    crea al publicar su provider, puede no estar migrada).
                if (Schema::hasTable('personal_access_tokens')) {
                    DB::table('personal_access_tokens')
                        ->where('tokenable_type', User::class)
                        ->where('tokenable_id', $userId)
                        ->delete();
                }

                // 7) payment_methods y wallet_transactions se borran en
                //    cascada por la FK (cascadeOnDelete) al borrar el user.
                //    Lo hacemos explícito antes del force-delete para que el
                //    log de auditoría pueda contar filas afectadas.
                $paymentsCount = DB::table('payment_methods')->where('user_id', $userId)->count();
                $walletCount = DB::table('wallet_transactions')->where('user_id', $userId)->count();
                DB::table('payment_methods')->where('user_id', $userId)->delete();
                DB::table('wallet_transactions')->where('user_id', $userId)->delete();

                // 7b) Carrito persistente del usuario (carts + cart_items).
                //    Estado pre-compra, sin valor contable: borrado físico.
                $cartId = DB::table('carts')->where('user_id', $userId)->value('id');
                if ($cartId) {
                    DB::table('cart_items')->where('cart_id', $cartId)->delete();
                    DB::table('carts')->where('id', $cartId)->delete();
                }

                // 7c) Liquidaciones (settlements): NO se borran (traza
                //    contable/fiscal). Se anonimizan desvinculando user_id
                //    (nullOnDelete lo haría igual, pero lo hacemos explícito
                //    para el conteo de auditoría). El asiento sigue vinculado
                //    al pedido, que ya tiene sus PII anonimizados.
                $settlementsCount = DB::table('settlements')->where('user_id', $userId)->count();
                DB::table('settlements')->where('user_id', $userId)->update(['user_id' => null]);

                // 8) Borrado físico del usuario. No usa soft deletes: el
                //    registro desaparece de la tabla users.
                $user->forceDelete();

                // 9) Auditoría: registramos el evento para cumplimiento.
                Log::channel(config('audit.log_channel', 'stack'))->info('RGPD: cuenta de usuario eliminada', [
                    'user_id' => $userId,
                    'email' => $userEmail,
                    'deleted_at' => now()->toDateTimeString(),
                    'ip' => request()?->ip(),
                    'payment_methods_deleted' => $paymentsCount,
                    'wallet_transactions_deleted' => $walletCount,
                    'cart_deleted' => $cartId !== null,
                    'settlements_anonymized' => $settlementsCount,
                ]);
            });

            return ['ok' => true];
        } catch (\Throwable $e) {
            Log::error('RGPD: fallo al eliminar cuenta de usuario #'.$userId.': '.$e->getMessage(), [
                'user_id' => $userId,
                'email' => $userEmail,
                'started_at' => $startedAt->toDateTimeString(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
