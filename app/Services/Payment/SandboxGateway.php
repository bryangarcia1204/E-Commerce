<?php

namespace App\Services\Payment;

use App\Models\PaymentMethod;
use App\Services\Payment\Contracts\PaymentGateway;
use Illuminate\Http\Request;

/**
 * Gateway de SIMULACIÓN determinista (sandbox) para local/demos.
 *
 * Se activa automáticamente cuando NO hay STRIPE_SECRET configurado, de
 * modo que el flujo completo de checkout con tarjeta (validación, cobro,
 * pedido pagado, settlements, método guardado) se puede probar sin claves.
 *
 * Comportamiento (mismas reglas que las tarjetas de test de Stripe):
 *   - Luhn + longitud 13-19 dígitos: si no pasa → "número no válido".
 *   - 4242 4242 4242 4242 (o cualquier Luhn-válida no listada) → éxito.
 *   - 4000 0000 0000 0002 → rechazada por el banco.
 *   - 4000 0000 0000 9995 → saldo insuficiente.
 *   - 4000 0000 0000 0069 → caducada.
 * Los tokens guardados (demo_*) cobran con éxito salvo last4 en 0002/9995.
 *
 * NO usar en producción: no contacta con ningún gateway real.
 */
class SandboxGateway implements PaymentGateway
{
    /** Tarjetas de test → código de rechazo. */
    private const DECLINED_CARDS = [
        '4000000000000002' => 'card_declined',
        '4000000000009995' => 'insufficient_funds',
        '4000000000000069' => 'expired_card',
    ];

    public function name(): string
    {
        return 'sandbox';
    }

    public function charge(
        int $amountCents,
        string $currency,
        array $card = [],
        ?PaymentMethod $savedMethod = null,
        array $metadata = []
    ): PaymentResult {
        if ($amountCents <= 0) {
            return PaymentResult::failure('Importe no válido.', 'invalid_amount');
        }

        // Cobro con método guardado: token demo_* con last4 determinista.
        if ($savedMethod !== null && $savedMethod->token) {
            if (in_array($savedMethod->last4, ['0002', '9995'], true)) {
                return PaymentResult::failure(
                    $savedMethod->last4 === '0002'
                        ? 'La tarjeta fue rechazada por el banco emisor.'
                        : 'La tarjeta no tiene saldo suficiente.',
                    $savedMethod->last4 === '0002' ? 'card_declined' : 'insufficient_funds'
                );
            }

            return PaymentResult::success(
                'sbx_pi_' . bin2hex(random_bytes(8)),
                ['sandbox' => true, 'saved_method' => $savedMethod->id]
            );
        }

        if (empty($card['number'])) {
            return PaymentResult::failure('Faltan los datos de la tarjeta.', 'missing_card');
        }

        $number = preg_replace('/\D+/', '', (string) $card['number']);

        if (strlen($number) < 13 || strlen($number) > 19) {
            return PaymentResult::failure('El número de tarjeta no es válido.', 'invalid_number');
        }

        if (! $this->luhnValid($number)) {
            return PaymentResult::failure('El número de tarjeta no es válido.', 'invalid_number');
        }

        // CVC: 3-4 dígitos.
        $cvc = preg_replace('/\D+/', '', (string) ($card['cvc'] ?? ''));

        if (strlen($cvc) < 3 || strlen($cvc) > 4) {
            return PaymentResult::failure('El código de seguridad (CVC) no es correcto.', 'invalid_cvc');
        }

        // Caducidad: mes 1-12 y no vencida (año en 2 o 4 dígitos).
        $month = (int) ($card['exp_month'] ?? 0);
        $year  = (int) ($card['exp_year'] ?? 0);

        if ($month < 1 || $month > 12) {
            return PaymentResult::failure('La fecha de caducidad no es válida.', 'invalid_expiry');
        }

        $year += $year < 100 ? 2000 : 0;

        if ($year < now()->year || ($year === now()->year && $month < now()->month)) {
            return PaymentResult::failure('La tarjeta ha caducado.', 'expired_card');
        }

        // Tarjetas de rechazo explícitas.
        if (isset(self::DECLINED_CARDS[$number])) {
            $code = self::DECLINED_CARDS[$number];

            return PaymentResult::failure(
                match ($code) {
                    'card_declined'      => 'La tarjeta fue rechazada por el banco emisor.',
                    'insufficient_funds' => 'La tarjeta no tiene saldo suficiente.',
                    'expired_card'       => 'La tarjeta ha caducado.',
                },
                $code
            );
        }

        // Éxito: id de "intento de pago" local, trazable en external_refs.
        return PaymentResult::success('sbx_pi_' . bin2hex(random_bytes(8)), [
            'sandbox' => true,
            'last4'   => substr($number, -4),
        ]);
    }

    public function refund(string $transactionId, int $amountCents): bool
    {
        // En sandbox todo "refund" local triunfa salvo id inexistente.
        return str_starts_with($transactionId, 'sbx_pi_') || str_starts_with($transactionId, 'pi_');
    }

    public function verifyWebhook(Request $request): ?array
    {
        // El sandbox no recibe webhooks reales: firma no verificable.
        return null;
    }

    private function luhnValid(string $number): bool
    {
        $sum = 0;
        $len = strlen($number);
        $alt = false;

        for ($i = $len - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($alt) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }
}
