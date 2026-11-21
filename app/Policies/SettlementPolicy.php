<?php

namespace App\Policies;

use App\Models\Settlement;
use App\Models\User;

/**
 * Las liquidaciones son datos financieros operativos: sólo el admin las
 * gestiona (ver listado, marcar comisiones como liquidadas). El cliente no
 * accede al libro de liquidaciones — su vista de pedido ya le muestra su
 * estado. Las filas no contienen secretos del usuario.
 */
class SettlementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Settlement $settlement): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Settlement $settlement): bool
    {
        return $user->isAdmin();
    }
}
