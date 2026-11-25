<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Payment\PaymentService;
use App\Services\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de la pasarela de pago (Stripe). Confirma pagos asíncronos:
 * payment_intent.succeeded / payment_intent.payment_failed.
 *
 * Seguridad: la firma (Stripe-Signature, HMAC-SHA256 con tolerancia de 5
 * min contra replay) se verifica ANTES de procesar nada; sin firma válida
 * se responde 400 y Stripe reintentará.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private SettlementService $settlements,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $this->payments->verifyWebhook($request);

        if ($payload === null) {
            Log::warning('Stripe webhook: firma inválida o ausente', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $payload['type'] ?? null;
        $intent = is_array($payload['data']['object'] ?? null) ? $payload['data']['object'] : [];

        $intentId = is_string($intent['id'] ?? null) ? $intent['id'] : null;

        if (! $intentId) {
            return response()->json(['status' => 'ignored']);
        }

        // Localizamos el pedido por la transacción registrada en checkout.
        // external_refs es JSON: filtramos por LIKE y confirmamos en PHP
        // para evitar falsos positivos.
        $order = Order::query()
            ->whereNotNull('external_refs')
            ->where('external_refs', 'like', '%' . $intentId . '%')
            ->get()
            ->first(fn (Order $o) => data_get($o->external_refs, 'payment.transaction_id') === $intentId);

        if (! $order) {
            Log::info('Stripe webhook: pedido no encontrado para el intent', [
                'intent' => $intentId,
                'event'  => $event,
            ]);

            // 200 para que Stripe no reintente eternamente.
            return response()->json(['status' => 'ignored']);
        }

        switch ($event) {
            case 'payment_intent.succeeded':
                if ($order->payment_status !== Order::PAYMENT_PAID) {
                    $order->update([
                        'payment_status' => Order::PAYMENT_PAID,
                        'external_refs'  => array_merge((array) $order->external_refs, [
                            'payment' => [
                                'gateway'        => $this->payments->gateway()->name(),
                                'transaction_id' => $intentId,
                                'paid_at'        => now()->toIso8601String(),
                            ],
                        ]),
                    ]);

                    $this->settlements->settleReceivable($order, $intentId);

                    Log::info('Stripe webhook: pedido marcado como pagado', [
                        'order'   => $order->number,
                        'intent'  => $intentId,
                    ]);
                }
                break;

            case 'payment_intent.payment_failed':
                if ($order->payment_status !== Order::PAYMENT_PAID) {
                    $order->update(['payment_status' => Order::PAYMENT_FAILED]);

                    Log::warning('Stripe webhook: pago fallido', [
                        'order'  => $order->number,
                        'intent' => $intentId,
                    ]);
                }
                break;
        }

        return response()->json(['status' => 'ok']);
    }
}
