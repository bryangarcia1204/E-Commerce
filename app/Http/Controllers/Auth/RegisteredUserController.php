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
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            // El rol se asigna por defecto en la BD ('customer'); lo fijamos
            // explícitamente por seguridad si la columna no tuviera default.
            'role'     => User::ROLE_CUSTOMER,
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
