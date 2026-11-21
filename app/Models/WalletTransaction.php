<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Movimiento inmutable del ledger de la billetera. Append-only: nunca se
 * edita ni borra directamente; el saldo actual se reconstruye sumando.
 * El borrado de cuenta elimina estas filas en cascada (constrained).
 */
class WalletTransaction extends Model
{
    use HasFactory;

    public const DIRECTION_CREDIT = 'credit';
    public const DIRECTION_DEBIT = 'debit';

    public const TYPE_PROMO_CREDIT = 'promo_credit';
    public const TYPE_CASHBACK = 'cashback';
    public const TYPE_REFUND = 'refund';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'user_id', 'direction', 'type', 'amount', 'balance_after',
        'currency', 'description', 'source_type', 'source_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
