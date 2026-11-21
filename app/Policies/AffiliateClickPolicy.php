<?php

namespace App\Policies;

use App\Models\AffiliateClick;
use App\Models\User;

/**
 * Clics de afiliación: el propietario puede ver los suyos; el admin puede ver
 * todos (analytics de comisiones). No contienen secretos: sólo IP/sesión y
 * el producto canalizado.
 */
class AffiliateClickPolicy
{
    public function view(User $user, AffiliateClick $click): bool
    {
        return ($click->user_id && $user->id === $click->user_id) || $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
