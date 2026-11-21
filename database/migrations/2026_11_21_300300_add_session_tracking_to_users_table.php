<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de sesión del usuario: cuándo inició la sesión actual y cuándo fue
 * su último login. Permite mostrar "sesión iniciada" + "tiempo transcurrido"
 * y detectar inactividad. El status online se deriva de la tabla sessions
 * (last_activity reciente); aquí guardamos el arranque de la sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Inicio de la sesión actual (se actualiza en cada Login event).
            $table->timestamp('session_started_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('session_started_at');
        });
    }
};
