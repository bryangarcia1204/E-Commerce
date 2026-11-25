<?php

namespace App\Services\Payment;

use App\Models\PaymentMethod;
use App\Services\Payment\Contracts\PaymentGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gateway real contra la API REST de Stripe (sin SDK: Laravel HTTP client).
 *
 * Flujo implementado:
 *  1. Si el usuario re-introdujo la tarjeta, se tokeniza server-side con
 *     POST /v1/tokens (válido con claves de TEST; en producción usa
 *     Stripe.js/Elements y guarda el pm_xxx en PaymentMethod::token).
 *  2. Se crea y CONFIRMA un PaymentIntent con POST /v1/payment_intents
 *     (amount en céntimos, currency, metadata con el nº de pedido).
 *  3. La respuesta se mapea a PaymentResult; los errores de tarjeta
 *     (card_declined, insufficient_funds...) se traducen a mensajes
 *     aptos para el usuario, sin exponer detalles internos del gateway.
 *
 * Nunca se persisten el PAN ni el CVC: del número sólo viaja al gateway.
 */
class StripeGateway implements PaymentGateway
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function name(): string
    {
        return 'stripe';
    }

    public function charge(
        int $amountCents,
        string $currency,
        array $card = [],
        ?PaymentMethod $savedMethod = null,
        array $metadata = []
    ): PaymentResult {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            return PaymentResult::failure(
                'La pasarela de pago no está configurada (falta STRIPE_SECRET).',
                'gateway_not_configured'
            );
        }

        $params = [
            'amount'   => $amountCents,
            'currency' => strtolower($currency),
            'confirm'  => 'true',
            'automatic_payment_methods[enabled]'         => 'true',
            'automatic_payment_methods[allow_redirects]' => 'never',
        ];

        foreach ($metadata as $key => $value) {
            $params['metadata[' . $key . ']'] = (string) $value;
        }

        // ¿Método guardado (token opaco pm_.../tok_...) o tarjeta nueva?
        if ($savedMethod !== null && $savedMethod->token) {
            $params['payment_method'] = $savedMethod->token;

            return $this->createIntent($params, $secret);
        }

        if (empty($card['number'])) {
            return PaymentResult::failure('Faltan los datos de la tarjeta.', 'missing_card');
        }

        // Tokenización server-side de la tarjeta del formulario.
        $token = $this->createCardToken($card, $secret);

        if (! $token->successful()) {
            return $this->mapError((array) $token->json());
        }

        $cardTokenId = $token->json('id');

        if (! is_string($cardTokenId) || $cardTokenId === '') {
            return PaymentResult::failure('No se pudo tokenizar la tarjeta.', 'tokenization_failed');
        }

        $params['payment_method_data[type]']        = 'card';
        $params['payment_method_data[card][token]'] = $cardTokenId;

        if (! empty($card['holder'])) {
            $params['payment_method_data[billing_details][name]'] = (string) $card['holder'];
        }

        return $this->createIntent($params, $secret);
    }

    public function refund(string $transactionId, int $amountCents): bool
    {
        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            return false;
        }

        try {
            return Http::asForm()
                ->withBasicAuth($secret, '')
                ->timeout(30)
                ->post(self::API_BASE . '/refunds', [
                    'payment_intent' => $transactionId,
                    'amount'         => $amountCents,
                ])
                ->successful();
        } catch (\Throwable $e) {
            Log::error('Stripe refund falló: ' . $e->getMessage());

            return false;
        }
    }

    public function verifyWebhook(Request $request): ?array
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '') {
            return null;
        }

        // Cabecera Stripe-Signature: "t=timestamp,v1=firma,..."
        $header = (string) $request->header('Stripe-Signature');

        if ($header === '') {
            return null;
        }

        $parts = [];

        foreach (explode(',', $header) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, null);

            if ($key !== null && $value !== null) {
                $parts[$key][] = $value;
            }
        }

        $timestamp = $parts['t'][0] ?? null;
        $signatures = $parts['v1'] ?? [];

        if (! $timestamp || $signatures === []) {
            return null;
        }

        // Tolerancia de 5 minutos contra replay.
        if (abs(time() - (int) $timestamp) > 300) {
            return null;
        }

        $payload = $request->getContent();
        $signed  = $timestamp . '.' . $payload;

        foreach ($signatures as $signature) {
            if (hash_equals(hash_hmac('sha256', $signed, $secret), $signature)) {
                $decoded = json_decode($payload, true);

                return is_array($decoded) ? $decoded : null;
            }
        }

        return null;
    }

    /**
     * Crea (y confirma) el PaymentIntent y mapea la respuesta.
     */
    private function createIntent(array $params, string $secret): PaymentResult
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth($secret, '')
                ->timeout(30)
                ->post(self::API_BASE . '/payment_intents', $params);
        } catch (\Throwable $e) {
            Log::error('Stripe PaymentIntent falló (red): ' . $e->getMessage());

            return PaymentResult::failure(
                'No se pudo contactar con la pasarela de pago. Inténtalo de nuevo en unos minutos.',
                'network_error'
            );
        }

        $json = (array) $response->json();

        if (! $response->successful()) {
            return $this->mapError($json);
        }

        $status   = $json['status'] ?? null;
        $intentId = $json['id'] ?? null;

        // Con confirm=true y sin 3DS/redirects: succeeded o processing.
        if (in_array($status, ['succeeded', 'processing'], true) && is_string($intentId)) {
            return PaymentResult::success($intentId, ['status' => $status]);
        }

        // requires_action: el pago necesita 3D Secure y no se completa en
        // este flujo server-side; lo tratamos como fallo controlado para
        // no marcar el pedido como pagado.
        return PaymentResult::failure(
            'El pago requiere autenticación adicional (3D Secure) y no pudo completarse automáticamente.',
            is_string($status) ? $status : 'requires_action',
            $json
        );
    }

    private function createCardToken(array $card, string $secret): Response
    {
        return Http::asForm()
            ->withBasicAuth($secret, '')
            ->timeout(30)
            ->post(self::API_BASE . '/tokens', [
                'card[number]'    => preg_replace('/\D+/', '', (string) $card['number']),
                'card[exp_month]' => (int) ($card['exp_month'] ?? 0),
                'card[exp_year]'  => (int) ($card['exp_year'] ?? 0),
                'card[cvc]'       => (string) ($card['cvc'] ?? ''),
            ]);
    }

    /**
     * Mapea un error de la API de Stripe a PaymentResult con mensaje apto
     * para usuario. Nunca se exponen detalles internos del gateway.
     */
    private function mapError(array $json): PaymentResult
    {
        $error = is_array($json['error'] ?? null) ? $json['error'] : [];

        $code = (string) ($error['code'] ?? $error['type'] ?? 'card_error');
        $declineCode = (string) ($error['decline_code'] ?? '');

        $message = match ($declineCode !== '' ? $declineCode : $code) {
            'card_declined', 'generic_decline'  => 'La tarjeta fue rechazada por el banco emisor.',
            'insufficient_funds'                => 'La tarjeta no tiene saldo suficiente.',
            'expired_card'                      => 'La tarjeta ha caducado.',
            'incorrect_cvc', 'invalid_cvc'      => 'El código de seguridad (CVC) no es correcto.',
            'incorrect_number', 'invalid_number'=> 'El número de tarjeta no es válido.',
            'processing_error'                  => 'Error temporal procesando el pago. Inténtalo de nuevo.',
            'rate_limited'                      => 'Demasiados intentos seguidos. Espera un momento e inténtalo de nuevo.',
            default                             => 'El pago no pudo completarse. Prueba con otra tarjeta o método de pago.',
        };

        return PaymentResult::failure($message, $code, ['error' => $error]);
    }
}
