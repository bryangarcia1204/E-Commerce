<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Payment\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PaymentService — orquestador del cobro real de pedidos.
 *
 * Selecciona el gateway automáticamente:
 *  - STRIPE_SECRET configurado → StripeGateway (API REST real).
 *  - Si no → SandboxGateway (determinista, para local/demos).
 *
 * Responsable de: cobrar el importe "chargeable" de un pedido (productos
 * propios + envío; el dropshipping se paga en la tienda externa), registrar
 * el identificador de transacción en Order::external_refs y guardar (si el
 * usuario lo pide) la tarjeta como PaymentMethod (token opaco + last4).
 */
class PaymentService
{
    private ?PaymentGateway $gatewayInstance = null;

    public function gateway(): PaymentGateway
    {
        return $this->gatewayInstance ??= $this->resolveGateway();
    }

    /** ¿Estamos usando la pasarela real o el sandbox? */
    public function isSandbox(): bool
    {
        return $this->gateway()->name() === 'sandbox';
    }

    /**
     * Cobra un pedido con tarjeta.
     *
     * @param  Order               $order       Pedido ya creado (se usa su nº
     *                                          como metadata idempotente).
     * @param  float               $amountEuros Importe chargeable en euros.
     * @param  array               $card        number/exp_month/exp_year/cvc/holder.
     * @param  PaymentMethod|null  $savedMethod Método guardado del usuario.
     * @return PaymentResult
     */
    public function chargeOrder(
        Order $order,
        float $amountEuros,
        array $card = [],
        ?PaymentMethod $savedMethod = null
    ): PaymentResult {
        $cents = (int) round($amountEuros * 100);

        if ($cents <= 0) {
            return PaymentResult::failure('El importe a cobrar debe ser mayor que 0.', 'invalid_amount');
        }

        $result = $this->gateway()->charge(
            $cents,
            (string) config('services.stripe.currency', 'eur'),
            $card,
            $savedMethod,
            [
                'order_number' => $order->number,
                'order_id'     => (string) $order->id,
                'user_id'      => (string) $order->user_id,
            ]
        );

        if ($result->success) {
            Log::info('Pago con tarjeta aceptado', [
                'order'         => $order->number,
                'gateway'       => $this->gateway()->name(),
                'transaction'   => $result->transactionId,
                'amount_cents'  => $cents,
            ]);
        } else {
            Log::warning('Pago con tarjeta rechazado', [
                'order'    => $order->number,
                'gateway'  => $this->gateway()->name(),
                'code'     => $result->errorCode,
            ]);
        }

        return $result;
    }

    /** Guarda una tarjeta nueva como método de pago (token opaco + last4). */
    public function storeCardMethod(
        int $userId,
        array $card,
        bool $makeDefault = false
    ): PaymentMethod {
        $number = preg_replace('/\D+/', '', (string) ($card['number'] ?? ''));
        $last4  = substr($number, -4);
        $provider = $this->detectBrand($number);

        if ($makeDefault) {
            PaymentMethod::where('user_id', $userId)->update(['is_default' => false]);
        }

        return PaymentMethod::create([
            'user_id'    => $userId,
            'type'       => PaymentMethod::TYPE_CARD,
            'provider'   => $provider,
            'last4'      => $last4 ?: null,
            'expires_at' => sprintf('%02d/%02d', (int) ($card['exp_month'] ?? 1), ((int) ($card['exp_year'] ?? now()->format('y')) % 100)),
            // En Stripe real: pm_/tok_ devuelto por el gateway. En sandbox:
            // token local marcado como demo.
            'token'      => 'demo_' . \Illuminate\Support\Str::uuid()->toString(),
            'is_default' => $makeDefault,
        ]);
    }

    /** Devuelve el importe de un PaymentIntent ya cobrado. */
    public function refund(Order $order, float $amountEuros): bool
    {
        $transactionId = data_get($order->external_refs, 'payment.transaction_id');

        if (! is_string($transactionId) || $transactionId === '') {
            return false;
        }

        return $this->gateway()->refund($transactionId, (int) round($amountEuros * 100));
    }

    /** Verifica el webhook del gateway activo (firma + decodificado). */
    public function verifyWebhook(Request $request): ?array
    {
        return $this->gateway()->verifyWebhook($request);
    }

    private function resolveGateway(): PaymentGateway
    {
        $secret = trim((string) config('services.stripe.secret'));

        return $secret !== '' && $secret !== 'sk_test_xxx'
            ? app(StripeGateway::class)
            : app(SandboxGateway::class);
    }

    private function detectBrand(string $number): string
    {
        return match (true) {
            str_starts_with($number, '4')                => 'visa',
            str_starts_with($number, '5')
                || str_starts_with($number, '2')         => 'mastercard',
            str_starts_with($number, '34')
                || str_starts_with($number, '37')        => 'amex',
            str_starts_with($number, '6')                => 'maestro',
            default                                     => 'card',
        };
    }
}
