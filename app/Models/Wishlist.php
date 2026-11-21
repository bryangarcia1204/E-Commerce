<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wishlist — un producto marcado como favorito por un usuario.
 *
 * Tabla `wishlists` (par único user_id+product_id). El usuario accede sólo a
 * sus filas (scope por auth()->id() en el controlador). No hay policy propia
 * porque el acceso se acota siempre al usuario en sesión: nunca se expone un
 * id de fila ajena por la URL (las rutas operan por product_id, y el toggle
 * inserta/borra la fila del usuario actual).
 */
class Wishlist extends Model
{
    use HasFactory;

    protected $table = 'wishlists';

    protected $fillable = ['user_id', 'product_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
