<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Gestión del formulario de contacto del footer. Persiste el mensaje en
 * contact_messages (para trazabilidad/respuesta) y notifica por email al
 * equipo de soporte. Aplica rate-limiting en la ruta (throttle:web) y
 * validación server-side. El mensaje se sanitiza al guardarlo para evitar
 * XSS persistente si se renderiza en un panel admin.
 */
class ContactController extends Controller
{
    public function create(): View
    {
        return view('pages.contacto');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'subject' => ['required', 'string', 'in:'.implode(',', array_keys(ContactMessage::SUBJECTS))],
            'topic' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:3000'],
            // Honeypot anti-spam: campo oculto que un bot rellenaría. Si
            // llega con valor, descartamos silenciosamente.
            'website' => ['nullable', 'string'],
        ]);

        // Honeypot: si el campo oculto trae contenido, es un bot.
        if (! empty($data['website'])) {
            return back()->with('success', '¡Gracias! Tu mensaje ha sido enviado.');
        }

        $user = $request->user();

        $message = ContactMessage::create([
            'user_id' => $user?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'topic' => $data['topic'] ?? null,
            // strip_tags para evitar XSS persistente en el panel admin.
            'message' => e(strip_tags($data['message'])),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);

        // Notificación por email (no bloqueante). En pre-producción
        // puedes no tener SMTP configurado; lo logueamos y seguimos.
        try {
            Mail::raw(
                "Nuevo mensaje de contacto #{$message->id}\n\n".
                "De: {$message->name} <{$message->email}>\n".
                "Asunto: ".ContactMessage::SUBJECTS[$message->subject] ?? $message->subject."\n".
                "Tema: {$message->topic}\n\n".
                $message->message,
                function ($m) use ($message) {
                    $m->to(config('mail.support_address', 'soporte@moda.local'))
                      ->subject('[MODA] Mensaje de contacto #'.$message->id);
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Contacto: no se pudo enviar email de notificación: '.$e->getMessage());
        }

        return back()->with('success', '¡Gracias! Tu mensaje ha sido enviado. Te responderemos pronto.');
    }
}
