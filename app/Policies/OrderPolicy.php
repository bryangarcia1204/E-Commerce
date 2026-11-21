<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Un cliente sólo puede ver sus propios pedidos. Un admin puede ver
     * cualquier pedido. Esto bloquea el IDOR que existía en checkout/success
     * y en cualquier futura vista de detalle de pedido.
     */
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->isAdmin();
    }

    /**
     * Listar pedidos (historial): cualquier usuario autenticado.
     * El filtrado por user_id se hace en el controlador.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Sólo el admin puede cambiar el estado de un pedido.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
