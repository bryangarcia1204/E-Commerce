@extends('layouts.app')
@section('title', 'Contacto — MODA')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-14">
    <h1 class="text-3xl font-black mb-2">Contacto</h1>
    <p class="text-sm text-gray-500 mb-8">Respondemos en un máximo de 48 h laborables. Para incidencias con un pedido, indica su número.</p>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm mb-6">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('contacto.store') }}" class="space-y-5">
        @csrf

        {{-- Honeypot anti-spam: campo oculto visualmente, un bot lo rellenaría --}}
        <div class="hidden" aria-hidden="true">
            <label>No rellenar: <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold mb-1">Nombre *</label>
                <input type="text" name="name" value="{{ old('name', auth()->user()?->name) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-black focus:ring-0">
                @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Email *</label>
                <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-black focus:ring-0">
                @error('email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold mb-1">Asunto *</label>
                <select name="subject" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-black focus:ring-0">
                    @foreach(\App\Models\ContactMessage::SUBJECTS as $slug => $label)
                        <option value="{{ $slug }}" @selected(old('subject') === $slug)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('subject') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-semibold mb-1">Tema / nº de pedido</label>
                <input type="text" name="topic" value="{{ old('topic') }}" placeholder="Ej. Pedido #000123"
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-black focus:ring-0">
                @error('topic') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold mb-1">Mensaje *</label>
            <textarea name="message" rows="6" required
                      class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-black focus:ring-0">{{ old('message') }}</textarea>
            @error('message') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                class="bg-black text-white px-6 py-3 rounded-lg font-semibold hover:bg-gray-800 transition">
            Enviar mensaje
        </button>
    </form>
</div>
@endsection
