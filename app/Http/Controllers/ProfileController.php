<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AccountDeletionService $deleter) {}

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user()->load('paymentMethods');

        return view('profile.edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account — GDPR right to be forgotten.
     *
     * Requiere confirmación con contraseña. El borrado es físico y en
     * cascada (tokens de pago, billetera, sesiones, etc.); los pedidos
     * se conservan para fines contables pero se anonimizan sus PII.
     * Ver AccountDeletionService para el detalle.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
            // Confirmación tipada: el usuario debe escribir "ELIMINAR" para
            // evitar borrados accidentales por click rápido.
            'confirm' => ['required', 'string', 'in:ELIMINAR'],
        ], [
            'confirm.in' => 'Para confirmar, escribe exactamente ELIMINAR.',
        ]);

        $user = $request->user();

        Auth::logout();

        $result = $this->deleter->delete($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (! $result['ok']) {
            return Redirect::to('/')
                ->with('error', 'No se pudo eliminar tu cuenta: '.$result['error'].'. Contacta con soporte.');
        }

        return Redirect::to('/')
            ->with('success', 'Tu cuenta y todos tus datos asociados han sido eliminados conforme al RGPD.');
    }
}
