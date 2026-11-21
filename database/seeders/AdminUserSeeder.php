<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea o actualiza el usuario administrador.
     *
     * - Email y nombre se leen de .env (ADMIN_EMAIL / ADMIN_NAME).
     * - Contraseña: si existe ADMIN_PASSWORD en .env se usa esa;
     *   en caso contrario se genera una aleatoria segura y se imprime
     *   por consola para que el operador la anote. Nunca se hardcodea
     *   una contraseña débil como "password".
     */
    public function run(): void
    {
        $email    = config('app.env') === 'production'
            ? env('ADMIN_EMAIL', 'admin@example.com')
            : env('ADMIN_EMAIL', 'admin@moda.test');

        $name     = env('ADMIN_NAME', 'Administrador');
        $password = env('ADMIN_PASSWORD');

        if (! $password || strlen($password) < 8) {
            $password = Str::random(24);
            // Avisamos al operador de la contraseña generada.
            $this->command->warn(
                "No se encontró ADMIN_PASSWORD en .env. ".
                "Se ha generado una contraseña aleatoria para {$email}:"
            );
            $this->command->info($password);
            $this->command->warn('Guárdala en un gestor de contraseñas y cámbiala cuanto antes.');
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'     => $name,
                'password' => Hash::make($password),
            ]
        )->promoteToAdmin();
    }
}
