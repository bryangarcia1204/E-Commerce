<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WalletTransaction;

/**
 * Aislamiento estricto del ledger de billetera: sólo el propietario puede ver
 * sus movimientos. NI SIQUIERA el admin puede leer el historial individual de
 * billetera de un usuario (es dato financiero sensible). El admin sólo ve
 * agregados (totals) en el dashboard, nunca el desglose por persona.
 */
class WalletTransactionPolicy
{
    public function view(User $user, WalletTransaction $tx): bool
    {
        return $user->id === $tx->user_id;
    }

    public function viewAny(User $user): bool
    {
        // Nadie lista billeteras ajenas desde este policy.
        return false;
    }
}
