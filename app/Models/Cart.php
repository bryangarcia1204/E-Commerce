<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Carrito persistente de un usuario autenticado. Sobrevive a reinicios y
 * caídas del servidor (a diferencia del carrito de sesión de invitados).
 * Un usuario tiene un único carrito (user_id unique).
 */
class Cart extends Model
{
    use HasFactory;

    protected $fillable = ['user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Total de unidades (no líneas) en el carrito.
     */
    public function getUnitsAttribute(): int
    {
        return (int) $this->items()->sum('quantity');
    }
}
