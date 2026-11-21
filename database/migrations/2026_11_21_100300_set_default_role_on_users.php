<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Para instalaciones existentes que ya tienen la columna `role` sin default,
 * fijamos el valor por defecto a 'customer'. Los usuarios ya creados sin rol
 * (NULL o vacío) se marcan también como 'customer'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')->whereNull('role')->orWhere('role', '')->update(['role' => 'customer']);

            // ALTER TABLE para fijar el DEFAULT en MySQL / PostgreSQL / SQLite.
            // En SQLite, ALTER COLUMN no permite cambiar el default directamente
            // sin recrear la tabla; lo intentamos y silenciamos errores.
            try {
                DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(255) NOT NULL DEFAULT 'customer'");
            } catch (\Throwable $e) {
                // PostgreSQL / SQLite: alternativa.
                try {
                    DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'customer'");
                } catch (\Throwable $e) {
                    // SQLite antiguo: no soporta ALTER COLUMN. No es bloqueante:
                    // el default se aplica en la nueva migración de creación.
                }
            }
        }
    }

    public function down(): void
    {
        // No revertimos el default a NULL por seguridad.
    }
};
