@extends('account.layouts.app')
@section('title', 'Mi perfil — MODA')

@section('content')
<h1 class="text-2xl font-black mb-6">Perfil y contraseña</h1>

<div class="grid md:grid-cols-2 gap-6">

    {{-- Datos --}}
    <form method="POST" action="{{ route('account.profile.update') }}"
          class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
        @csrf @method('PATCH')
        <h2 class="font-bold">Datos personales</h2>

        <div>
            <label class="block text-sm font-semibold mb-1">Nombre</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1">Email</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
            @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
            class="w-full bg-ink text-white rounded-lg py-2.5 font-semibold hover:bg-gray-800">
            Guardar cambios
        </button>
    </form>

    {{-- Contraseña --}}
    <form method="POST" action="{{ route('account.password.update') }}"
          class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
        @csrf @method('PATCH')
        <h2 class="font-bold">Cambiar contraseña</h2>

        <div>
            <label class="block text-sm font-semibold mb-1">Contraseña actual</label>
            <input type="password" name="current_password" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
            @error('current_password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1">Nueva contraseña</label>
            <input type="password" name="password" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
            @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1">Confirmar nueva contraseña</label>
            <input type="password" name="password_confirmation" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
        </div>

        <button type="submit"
            class="w-full bg-brand text-white rounded-lg py-2.5 font-semibold hover:bg-brand-dark">
            Actualizar contraseña
        </button>
    </form>

    {{-- Eliminar Cuenta --}}
    <form method="POST" action="{{ route('account.profile.destroy') }}" class="bg-white rounded-2xl shadow-sm p-6 md:col-span-2 space-y-4 border border-red-100"
        onsubmit="return confirm('¿Seguro que quieres eliminar tu cuenta? Esta acción no se puede deshacer.')">
        @csrf @method('DELETE')
        <h2 class="font-bold text-red-600">Eliminar cuenta</h2>
        <p class="text-sm text-gray-600">Esta acción es permanente. Se eliminarán tus datos y pedidos asociados.</p>

        <div>
            <label class="block text-sm font-semibold mb-1">Contraseña actual</label>
            <input type="password" name="password" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
            @error('password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="bg-red-600 text-white rounded-lg px-6 py-2.5 font-semibold hover:bg-red-700">
            Eliminar mi cuenta
        </button>
    </form>

    {{-- Métodos de pago guardados (tarjetas). Nunca se guarda el PAN ni el
         CVC: sólo los últimos 4 dígitos y un token opaco del gateway. --}}
    <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4 md:col-span-2">
        <h2 class="font-bold">Métodos de pago</h2>

        @php
            $paymentMethods = $user->paymentMethods()->orderByDesc('is_default')->get();
        @endphp

        <div class="space-y-2">
            @forelse($paymentMethods as $pm)
                <div class="flex items-center justify-between border border-gray-200 rounded-lg px-4 py-3">
                    <div class="flex items-center gap-3 text-sm">
                        <span class="font-medium">{{ $pm->label }}</span>
                        @if($pm->is_default)
                            <span class="text-[10px] uppercase tracking-wider bg-green-100 text-green-800 px-1.5 py-0.5 rounded">Predeterminado</span>
                        @endif
                        @if($pm->expires_at && $pm->type === 'card')
                            <span class="text-xs text-gray-500">caduca {{ $pm->expires_at }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        @unless($pm->is_default)
                            <form method="POST" action="{{ route('payment-methods.default', $pm) }}">
                                @csrf @method('PATCH')
                                <button class="text-gray-600 hover:text-black hover:underline">Predeterminar</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('payment-methods.destroy', $pm) }}"
                              onsubmit="return confirm('¿Eliminar este método de pago?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">No tienes métodos de pago guardados. Puedes guardar una tarjeta al finalizar una compra o añadirla aquí:</p>
            @endforelse
        </div>

        {{-- Añadir tarjeta: sólo last4-relevant data; el token se genera al
             pagar (en sandbox) o vía Stripe (en producción). --}}
        <form method="POST" action="{{ route('payment-methods.store') }}" class="grid md:grid-cols-4 gap-3 pt-2 border-t border-gray-100">
            @csrf
            <div class="md:col-span-4"><p class="text-sm font-semibold text-gray-700">Añadir tarjeta</p></div>
            <div>
                <label class="block text-xs font-semibold mb-1">Marca</label>
                <select name="provider" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-ink focus:ring-0">
                    <option value="visa">Visa</option>
                    <option value="mastercard">Mastercard</option>
                    <option value="amex">Amex</option>
                    <option value="maestro">Maestro</option>
                </select>
                @error('provider') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Últimos 4 dígitos</label>
                <input type="text" name="last4" inputmode="numeric" maxlength="4" placeholder="4242" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-ink focus:ring-0">
                @error('last4') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1">Caduca (MM/YY)</label>
                <input type="text" name="expires_at" placeholder="12/28" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-ink focus:ring-0">
                @error('expires_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                    <input type="checkbox" name="is_default" value="1">
                    <span>Predeterminada</span>
                </label>
            </div>
            <input type="hidden" name="type" value="{{ \App\Models\PaymentMethod::TYPE_CARD }}">
            <div class="md:col-span-4">
                <button type="submit" class="bg-ink text-white rounded-lg px-6 py-2.5 text-sm font-semibold hover:bg-gray-800">
                    Guardar tarjeta
                </button>
                <p class="text-[11px] text-gray-400 mt-2">
                    Nunca almacenamos el número completo ni el CVC: sólo los últimos 4 dígitos y un token del proveedor de pago.
                </p>
            </div>
        </form>
    </div>
</div>
@endsection