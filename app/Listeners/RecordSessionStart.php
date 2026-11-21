<?php

namespace App\Listeners;

use App\Services\CartService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Se dispara en Illuminate\Auth\Events\Login. Registra el inicio de la sesión
 * (session_started_at) y el último login (last_login_at) para que el usuario
 * pueda ver "sesión iniciada" + "tiempo transcurrido". Además migra el
 * carrito de sesión (invitado) al carrito persistente en BD del usuario.
 */
class RecordSessionStart
{
    public function __construct(private CartService $cart) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        // Actualizamos con query builder para no disparar eventos de modelo
        // ni sobrescribir campos modificados concurrentemente.
        DB::table('users')
            ->where('id', $user->id)
            ->update([
                'session_started_at' => now(),
                'last_login_at' => now(),
            ]);

        // El atributo en memoria se refresca para la request actual.
        $user->session_started_at = now();
        $user->last_login_at = now();

        // Migra el carrito de invitado (sesión) al carrito DB del usuario.
        $this->cart->migrateSessionToDatabase();
    }
}
