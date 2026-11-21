<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Asiento del libro de cuentas pendientes de liquidación dropshipping.
 * Permite ver el flujo de caja pendiente (por cobrar / por pagar / comisiones)
 * sin exponer los secretos individuales del usuario.
 */
class Settlement extends Model
{
    use HasFactory;

    public const TYPE_RECEIVABLE_CUSTOMER = 'receivable_from_customer';
    public const TYPE_COMMISSION_OWED = 'commission_owed_by_store';
    public const TYPE_PAYABLE_SUPPLIER = 'payable_to_supplier';

    public const TYPES = [
        self::TYPE_RECEIVABLE_CUSTOMER => 'Por cobrar (cliente)',
        self::TYPE_COMMISSION_OWED => 'Comisión por cobrar (tienda)',
        self::TYPE_PAYABLE_SUPPLIER => 'Por pagar (proveedor)',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_WRITTEN_OFF = 'written_off';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_SETTLED => 'Liquidado',
        self::STATUS_DISPUTED => 'Disputado',
        self::STATUS_WRITTEN_OFF => 'Cancelado',
    ];

    protected $fillable = [
        'order_id', 'user_id', 'type', 'amount', 'currency',
        'status', 'description', 'settled_at', 'external_ref',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }
}
