<?php

namespace App\Services\Payment;

/**
 * Resultado inmutable de un intento de cobro.
 */
final class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        /** ID de transacción del gateway (payment intent / referencia sandbox). */
        public readonly ?string $transactionId = null,
        /** Mensaje de error apto para el usuario (nunca datos sensibles). */
        public readonly ?string $errorMessage = null,
        /** Código de rechazo normalizado (card_declined, insufficient_funds...). */
        public readonly ?string $errorCode = null,
        /** Datos de la respuesta cruda para auditoría (sin PAN/CVC). */
        public readonly array $raw = [],
    ) {}

    public static function success(string $transactionId, array $raw = []): self
    {
        return new self(true, $transactionId, null, null, $raw);
    }

    public static function failure(string $errorMessage, ?string $errorCode = null, array $raw = []): self
    {
        return new self(false, null, $errorMessage, $errorCode, $raw);
    }
}
