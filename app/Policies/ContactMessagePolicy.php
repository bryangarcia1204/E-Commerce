<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

/**
 * Mensajes de contacto: el propietario puede ver los suyos; el admin puede
 * verlos todos (para responder/incidentar). El admin NO puede ver otros
 * secretos del usuario (tokens, billetera) —sólo el contenido del mensaje,
 * que el usuario envió voluntariamente.
 */
class ContactMessagePolicy
{
    public function view(User $user, ContactMessage $message): bool
    {
        return $user->id === $message->user_id || $user->isAdmin();
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ContactMessage $message): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ContactMessage $message): bool
    {
        return $user->isAdmin();
    }
}
