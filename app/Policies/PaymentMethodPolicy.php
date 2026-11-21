<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Policy que garantiza que un usuario sólo puede ver/modificar/borrar sus
 * propios métodos de pago. Previene IDOR: sin esto, cualquier usuario
 * autenticado podría leer el token de la tarjeta de otro adivinando el ID.
 */
class PaymentMethodPolicy
{
    public function view(User $user, PaymentMethod $method): bool
    {
        return $user->id === $method->user_id;
    }

    public function update(User $user, PaymentMethod $method): bool
    {
        return $user->id === $method->user_id;
    }

    public function delete(User $user, PaymentMethod $method): bool
    {
        return $user->id === $method->user_id;
    }

    /**
     * Un admin no puede leer los tokens de pago de otros usuarios desde
     * el front (sólo auditoría interna, fuera de este flujo).
     */
    public function viewAny(User $user): bool
    {
        return false;
    }
}
