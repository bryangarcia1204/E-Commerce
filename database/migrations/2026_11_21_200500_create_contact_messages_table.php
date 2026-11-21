<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes recibidos por el formulario de contacto del footer. Se guardan
 * en BD para trazabilidad y respuesta, y se notifican por email al equipo.
 * Si el usuario estaba autenticado, se vincula a su cuenta para poder
 * responder desde el panel. Los mensajes de usuarios borrados se eliminan
 * en cascada (constrained user_id) —GDPR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email', 150);
            // shipping | returns | press | partnership | other
            $table->string('subject', 40)->default('other');
            $table->string('topic', 120)->nullable();
            $table->text('message');
            // new | read | replied | archived
            $table->string('status', 20)->default('new');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
