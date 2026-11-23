<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Gestión de los métodos de pago guardados por el usuario autenticado.
 *
 * SEGURIDAD:
 *   - Cada acción autoriza vía PaymentMethodPolicy (el usuario sólo
 *     puede tocar sus propios métodos — anti IDOR).
 *   - NUNCA aceptamos el PAN completo ni el CVV. Del número de tarjeta
 *     sólo guardamos los últimos 4 (last4) y un token opaco que, en
 *     producción, devolvería la pasarela (Stripe/Braintree). En
 *     pre-producción generamos un UUID marcado como demo.
 *   - El campo `token` está en $hidden del modelo, así que nunca se
 *     serializa a la respuesta.
 */
class PaymentMethodController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $type = $request->input('type', PaymentMethod::TYPE_CARD);

        $rules = [
            'type' => ['required', Rule::in(array_keys(PaymentMethod::TYPES))],
            'is_default' => ['nullable', 'boolean'],
        ];

        if ($type === PaymentMethod::TYPE_CARD) {
            $rules['provider'] = ['required', 'string', 'in:visa,mastercard,amex,maestro'];
            $rules['last4'] = ['required', 'string', 'digits:4'];
            $rules['expires_at'] = ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'];
        } elseif ($type === PaymentMethod::TYPE_PAYPAL) {
            $rules['email'] = ['required', 'email', 'max:150'];
        }

        $data = $request->validate($rules);

        // Si marca este como default, quitamos el flag de los demás.
        $makeDefault = ($request->boolean('is_default')) || $user->paymentMethods()->count() === 0;

        $payload = [
            'user_id' => $user->id,
            'type' => $type,
            'provider' => $data['provider'] ?? null,
            'last4' => $data['last4'] ?? null,
            'email' => $data['email'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            // En demo: UUID prefijado. En prod: respuesta de la pasarela.
            'token' => 'demo_'.Str::uuid()->toString(),
            'is_default' => $makeDefault,
        ];

        if ($makeDefault) {
            $user->paymentMethods()->update(['is_default' => false]);
        }

        $user->paymentMethods()->create($payload);

        return back()->with('success', 'Método de pago guardado.');
    }

    public function setDefault(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorize('update', $paymentMethod);

        $user = $request->user();
        $user->paymentMethods()->update(['is_default' => false]);
        $paymentMethod->update(['is_default' => true]);

        // Refleja la preferencia también en el campo del usuario.
        $user->preferred_payment_method = match ($paymentMethod->type) {
            PaymentMethod::TYPE_CARD => User::PAYMENT_CARD,
            PaymentMethod::TYPE_PAYPAL => User::PAYMENT_PAYPAL,
            PaymentMethod::TYPE_WALLET => User::PAYMENT_WALLET,
            default => $user->preferred_payment_method,
        };
        $user->save();

        return back()->with('success', 'Método de pago predeterminado actualizado.');
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorize('delete', $paymentMethod);

        $wasDefault = $paymentMethod->is_default;
        $paymentMethod->delete();

        // Si borramos el default, promocionamos el más reciente.
        if ($wasDefault) {
            $next = $request->user()->paymentMethods()->latest()->first();
            $next?->update(['is_default' => true]);
        }

        return back()->with('success', 'Método de pago eliminado.');
    }
}
