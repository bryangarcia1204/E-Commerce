<?php

namespace App\Policies;

use App\Models\Cart;
use App\Models\User;

/**
 * El carrito de un usuario es privado: sólo el propietario puede verlo o
 * modificarlo. NI SIQUIERA el admin accede a carritos ajenos (no hay razón
 * operativa: el carrito es estado previo a compra, no un pedido). El
 * CartService ya filtra por user_id; esta policy es la red de seguridad
 * por si se añade una ruta que binda un Cart por route-model-binding.
 */
class CartPolicy
{
    public function view(User $user, Cart $cart): bool
    {
        return $user->id === $cart->user_id;
    }

    public function update(User $user, Cart $cart): bool
    {
        return $user->id === $cart->user_id;
    }

    public function delete(User $user, Cart $cart): bool
    {
        return $user->id === $cart->user_id;
    }

    public function viewAny(User $user): bool
    {
        return false; // nadie lista carritos ajenos
    }
}
