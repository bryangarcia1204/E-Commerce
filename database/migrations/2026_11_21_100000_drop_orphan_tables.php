<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina tablas huérfanas creadas por migraciones de pruebas previas:
 *   - "productos"  (nombre en español, esquema vacío, sin modelo)
 *   - "first"      (tabla de ejemplo vacía)
 *
 * La migración es idempotente: si las tablas no existen, no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['productos', 'first'] as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }
    }

    public function down(): void
    {
        // No recreamos tablas huérfanas intencionadamente.
    }
};
