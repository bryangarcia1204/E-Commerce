<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletEntry extends Model
{
    protected $fillable = [
        'wallet_id', 'amount', 'reason',
        'reference_type', 'reference_id',
        'idempotency_key', 'meta',
    ];

    protected $casts = [
        'amount' => 'integer',
        'meta'   => 'array',
    ];

    protected static function booted(): void
    {
        // Inmutabilidad real: cualquier intento de UPDATE o DELETE lanza excepción.
        static::updating(function () {
            throw new \RuntimeException('Las entradas del ledger son inmutables.');
        });
        static::deleting(function () {
            throw new \RuntimeException('Las entradas del ledger son inmutables.');
        });

        // amount nunca puede ser 0 (una entrada sin movimiento no tiene sentido)
        static::creating(function (self $entry) {
            if ((int) $entry->amount === 0) {
                throw new \InvalidArgumentException('El importe de una entrada no puede ser 0.');
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}