<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SettlementService — libro de cuentas pendientes de liquidación.
 *
 * Por cada pedido genera los asientos que reflejan el flujo de caja del
 * modelo dropshipping + afiliación:
 *
 *   - receivable_from_customer: lo que el cliente nos debe por productos
 *     propios + envío. Si pagó con billetera, se liquida al instante;
 *     si no, queda pendiente hasta confirmar el pago (tarjeta/PayPal/COD).
 *   - commission_owed_by_store: comisión que la tienda externa nos debe por
 *     cada venta dropshipping canalizada. Pendiente hasta que la tienda
 *     confirme la conversión (postback).
 *   - payable_to_supplier: lo que debemos al proveedor de los productos
 *     propios (si tienen cost_price). Pendiente hasta que se pague.
 *
 * Los asientos viven en la tabla `settlements` y permiten al admin ver el
 * flujo de caja pendiente de forma agregada, SIN exponer los secretos
 * individuales del usuario (tokens de pago, contraseñas, etc.).
 */
class SettlementService
{
    /**
     * Crea los asientos de un pedido dentro de la transacción del checkout.
     *
     * @param array{chargeable: float, commission_total: float, payable_supplier: float, wallet_paid: bool} $values
     */
    public function createForOrder(Order $order, array $values): void
    {
        $chargeable = (float) ($values['chargeable'] ?? 0);
        $commission = (float) ($values['commission_total'] ?? 0);
        $payable = (float) ($values['payable_supplier'] ?? 0);
        $walletPaid = (bool) ($values['wallet_paid'] ?? false);

        $rows = [];

        if ($chargeable > 0) {
            $rows[] = [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'type' => Settlement::TYPE_RECEIVABLE_CUSTOMER,
                'amount' => $chargeable,
                'status' => $walletPaid ? Settlement::STATUS_SETTLED : Settlement::STATUS_PENDING,
                'settled_at' => $walletPaid ? now() : null,
                'description' => 'Cobro al cliente (productos propios + envío)',
            ];
        }

        if ($commission > 0) {
            $rows[] = [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'type' => Settlement::TYPE_COMMISSION_OWED,
                'amount' => $commission,
                'status' => Settlement::STATUS_PENDING,
                'description' => 'Comisión de afiliación pendiente de confirmar por la tienda',
            ];
        }

        if ($payable > 0) {
            $rows[] = [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'type' => Settlement::TYPE_PAYABLE_SUPPLIER,
                'amount' => $payable,
                'status' => Settlement::STATUS_PENDING,
                'description' => 'Pago al proveedor por productos propios',
            ];
        }

        if (empty($rows)) {
            return;
        }

        try {
            // Insert masivo dentro de la transacción del checkout.
            DB::table('settlements')->insert(array_map(
                fn ($r) => $r + ['currency' => 'EUR', 'created_at' => now(), 'updated_at' => now()],
                $rows
            ));
        } catch (\Throwable $e) {
            // No abortamos el pedido por un fallo de asiento: lo logueamos
            // para conciliación manual. El pedido ya está creado.
            Log::error('Settlements: fallo al crear asientos del pedido #'.$order->id.': '.$e->getMessage());
        }
    }

    /**
     * Marca como liquidada la comisión de un pedido cuando la tienda externa
     * confirma la conversión (postback). En pre-producción se llama a mano
     * desde el panel admin.
     */
    public function settleCommission(Order $order, ?string $externalRef = null): void
    {
        Settlement::ofType(Settlement::TYPE_COMMISSION_OWED)
            ->where('order_id', $order->id)
            ->where('status', Settlement::STATUS_PENDING)
            ->update([
                'status' => Settlement::STATUS_SETTLED,
                'settled_at' => now(),
                'external_ref' => $externalRef,
            ]);
    }
}
