<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Contraseña reutilizada por el factory (los tests la usan al loguear).
     */
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            // 'role' se asigna por el default de la BD ('customer').
            // Como no está en $fillable, no lo incluimos aquí.
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Estado "admin": crea un usuario administrador. Asignamos el rol
     * a mano porque 'role' no es mass-assignable.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->promoteToAdmin();
        });
    }
}
