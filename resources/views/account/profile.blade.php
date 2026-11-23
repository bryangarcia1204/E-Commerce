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
</div>
@endsection