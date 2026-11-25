<?php

namespace App\Services\Payment\Contracts;

use App\Models\PaymentMethod;
use App\Services\Payment\PaymentResult;
use Illuminate\Http\Request;

/**
 * Contrato de una pasarela de pago (tarjeta).
 *
 * Implementaciones:
 *  - StripeGateway: llamadas reales a la API REST de Stripe (Laravel HTTP
 *    client, sin SDK). Requiere STRIPE_SECRET.
 *  - SandboxGateway: simulación determinista para local/demos sin claves
 *    (4242… éxito, 4000…0002 rechazada, 4000…9995 sin fondos).
 */
interface PaymentGateway
{
    /** Nombre corto del gateway ('stripe' | 'sandbox'). */
    public function name(): string;

    /**
     * Cobra (crea + confirma) un importe en céntimos.
     *
     * @param  int               $amountCents Importe > 0 en céntimos.
     * @param  string            $currency    'eur'...
     * @param  array             $card        Datos de tarjeta crudos del form
     *                                        (number, exp_month, exp_year, cvc, holder).
     * @param  PaymentMethod     $savedMethod Método guardado (token opaco) si el
     *                                        usuario no re-introdujo la tarjeta.
     * @param  array             $metadata    Metadatos que viajarán al gateway
     *                                        (order_number, user_id...).
     */
    public function charge(
        int $amountCents,
        string $currency,
        array $card = [],
        ?PaymentMethod $savedMethod = null,
        array $metadata = []
    ): PaymentResult;

    /** Devuelve un importe previamente cobrado (best-effort). */
    public function refund(string $transactionId, int $amountCents): bool;

    /** Verifica la firma de un webhook entrante y decodifica el payload. */
    public function verifyWebhook(Request $request): ?array;
}
