<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /** Devuelve (o crea) la wallet del usuario. */
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id]);
    }

    /** Saldo en céntimos. */
    public function balance(User $user): int
    {
        return $this->walletFor($user)->balance();
    }

    /**
     * Acredita saldo.
     *
     * @param int              $amountInCents Importe positivo (crédito).
     * @param string           $reason        Motivo legible ('refund', 'promo', ...).
     * @param Model|null       $reference     Modelo relacionado opcional (Order, AffiliateClick...).
     * @param string|null      $idempotencyKey Evita duplicados si se reintenta.
     * @param array            $meta          Datos extra para auditar.
     */
    public function credit(
        User $user,
        int $amountInCents,
        string $reason,
        ?Model $reference = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): WalletEntry {
        if ($amountInCents <= 0) {
            throw new \InvalidArgumentException('El importe a acreditar debe ser > 0.');
        }

        return $this->writeEntry($user, $amountInCents, $reason, $reference, $idempotencyKey, $meta);
    }

    /**
     * Debita saldo. Lanza excepción si no hay suficiente.
     */
    public function debit(
        User $user,
        int $amountInCents,
        string $reason,
        ?Model $reference = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): WalletEntry {
        if ($amountInCents <= 0) {
            throw new \InvalidArgumentException('El importe a debitar debe ser > 0.');
        }

        return $this->writeEntry($user, -$amountInCents, $reason, $reference, $idempotencyKey, $meta);
    }

    /**
     * Escribe una entrada garantizando concurrencia segura y saldo no negativo.
     * Todo ocurre en una transacción con lockForUpdate.
     */
    private function writeEntry(
        User $user,
        int $signedAmount,
        string $reason,
        ?Model $reference,
        ?string $idempotencyKey,
        array $meta
    ): WalletEntry {
        return DB::transaction(function () use ($user, $signedAmount, $reason, $reference, $idempotencyKey, $meta) {
            // 1. Idempotencia: si ya existe la key, devolvemos la existente
            if ($idempotencyKey) {
                $existing = WalletEntry::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            // 2. Bloquear la wallet para esta transacción
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
                ?? Wallet::create(['user_id' => $user->id]);

            // 3. Si es un débito, validar saldo DENTRO del lock
            if ($signedAmount < 0) {
                $current = (int) $wallet->entries()->sum('amount');
                if ($current + $signedAmount < 0) {
                    throw new \RuntimeException('Saldo insuficiente en la billetera MODA.');
                }
            }

            // 4. Insertar la entrada (solo INSERT; nunca UPDATE)
            return WalletEntry::create([
                'wallet_id'       => $wallet->id,
                'amount'          => $signedAmount,
                'reason'          => $reason,
                'reference_type'  => $reference ? get_class($reference) : null,
                'reference_id'    => $reference?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'meta'            => $meta ?: null,
            ]);
        });
    }

    public function applyMovement(
        User $user,
        string $direction,          // 'credit' | 'debit'
        string $type,               // 'purchase', 'refund', 'promo', 'adjustment', ...
        float $amountEuros,
        string $description,
        Model $source = null,
        ?string $idempotencyKey = null,
        array $meta = []
    ): WalletEntry {
        $cents = (int) round($amountEuros * 100);

        if ($cents <= 0) {
            throw new \InvalidArgumentException('El importe debe ser mayor que 0.');
        }

        $meta = array_merge($meta, ['type' => $type]);

        return $direction === 'debit'
            ? $this->debit($user, $cents, $description, $source, $idempotencyKey, $meta)
            : $this->credit($user, $cents, $description, $source, $idempotencyKey, $meta);
    }
}