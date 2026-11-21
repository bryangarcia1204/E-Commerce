<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Muestra el formulario de registro.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Crea un nuevo usuario (siempre con rol "customer").
     *
     * El campo 'role' no está en $fillable del modelo User, por lo que
     * aunque el atacante lo incluya en la petición, se ignora.
     *
     * Además captura datos opcionales de cliente (teléfono, dirección de
     * envío preferida y método de pago preferido) para acelerar futuros
     * checkouts. La billetera se inicia en 0 (default de la BD).
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                      => ['required', 'string', 'max:255'],
            'email'                     => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password'                  => ['required', 'confirmed', Rules\Password::defaults()],
            // Opcionales — no bloquean el registro si vienen vacíos.
            'phone'                     => ['nullable', 'string', 'max:30'],
            'default_shipping_address'  => ['nullable', 'string', 'max:500'],
            'preferred_payment_method'  => ['nullable', 'string', 'in:'.implode(',', array_keys(User::PAYMENTS))],
        ]);

        $user = User::create([
            'name'                      => $data['name'],
            'email'                     => $data['email'],
            'password'                  => Hash::make($data['password']),
            'phone'                     => $data['phone'] ?? null,
            'default_shipping_address'  => $data['default_shipping_address'] ?? null,
            'preferred_payment_method'  => $data['preferred_payment_method'] ?? User::PAYMENT_CARD,
            // El rol se asigna por defecto en la BD ('customer'); lo fijamos
            // explícitamente por seguridad si la columna no tuviera default.
            'role'                      => User::ROLE_CUSTOMER,
        ]);

        // Como 'role' NO está en $fillable, hay que asignarlo a mano.
        // (No-op si la BD ya tiene default 'customer'.)
        if ($user->role !== User::ROLE_CUSTOMER) {
            $user->role = User::ROLE_CUSTOMER;
            $user->save();
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
