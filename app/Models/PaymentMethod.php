<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Método de pago guardado por un usuario. NUNCA almacena PAN completo ni
 * CVV: sólo últimos 4 (last4) + un token opaco devuelto por la pasarela.
 * En pre-producción el token es un placeholder explicitamente marcado.
 */
class PaymentMethod extends Model
{
    use HasFactory;

    public const TYPE_CARD = 'card';
    public const TYPE_PAYPAL = 'paypal';
    public const TYPE_WALLET = 'wallet';

    public const TYPES = [
        self::TYPE_CARD => 'Tarjeta',
        self::TYPE_PAYPAL => 'PayPal',
        self::TYPE_WALLET => 'Billetera MODA',
    ];

    protected $fillable = [
        'user_id', 'type', 'provider', 'last4', 'token', 'email',
        'expires_at', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Sólo se devuelven los últimos 4 dígitos en cualquier serialización
     * (API, JSON de vista). El token nunca se expone al cliente.
     */
    protected $hidden = [
        'token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Etiqueta legible para la UI: "Visa •••• 4242" o "PayPal taylor@…".
     */
    public function getLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_CARD => trim(($this->provider ? ucfirst($this->provider).' ' : '').'•••• '.($this->last4 ?? '????')),
            self::TYPE_PAYPAL => 'PayPal '.($this->email ?? ''),
            self::TYPE_WALLET => 'Billetera MODA',
            default => ucfirst($this->type),
        };
    }

    /**
     * ¿Ha caducado? Sólo aplica a tarjetas con expires_at (MM/YY).
     */
    public function getIsExpiredAttribute(): bool
    {
        if (! $this->expires_at || $this->type !== self::TYPE_CARD) {
            return false;
        }
        // expires_at viene como "MM/YY". Último día del mes/año.
        [$month, $year] = explode('/', $this->expires_at) + [1 => null];
        if (! $month || ! $year) {
            return false;
        }
        $year = 2000 + (int) $year;
        try {
            return now()->startOfMonth()->gt(
                now()->createFromDate($year, (int) $month, 1)->endOfMonth()
            );
        } catch (\Throwable $e) {
            return false;
        }
    }
}
