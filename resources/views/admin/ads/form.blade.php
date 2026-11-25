@extends('admin.layouts.app')
@section('title', ($creating ? 'Nuevo' : 'Editar') . ' anuncio — Admin')
@section('heading', $creating ? 'Nuevo anuncio' : 'Editar anuncio')

@section('content')
<form method="POST"
      action="{{ $creating ? route('admin.ads.store') : route('admin.ads.update', $ad) }}"
      class="bg-white rounded-2xl shadow-sm p-6 space-y-4 max-w-3xl">
    @csrf
    @unless($creating) @method('PUT') @endunless

    <div class="grid md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre interno *</label>
            <input type="text" name="name" value="{{ old('name', $ad->name) }}" required
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Posición *</label>
            <select name="position" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                @foreach(\App\Models\Ad::POSITIONS as $slug => $label)
                    <option value="{{ $slug }}" @selected(old('position', $ad->position) === $slug)>{{ $label }}</option>
                @endforeach
            </select>
            @error('position') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo *</label>
            <select name="type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                @foreach(\App\Models\Ad::TYPES as $slug => $label)
                    <option value="{{ $slug }}" @selected(old('type', $ad->type) === $slug)>{{ $label }}</option>
                @endforeach
            </select>
            @error('type') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Proveedor</label>
            <input type="text" name="provider" value="{{ old('provider', $ad->provider ?? 'internal') }}" placeholder="internal"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            @error('provider') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Orden (menor = primero)</label>
            <input type="number" name="sort_order" min="0" max="255" value="{{ old('sort_order', $ad->sort_order ?? 0) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            @error('sort_order') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">URL de imagen (tipo imagen)</label>
            <input type="text" name="image_url" value="{{ old('image_url', $ad->image_url) }}" placeholder="{{ asset('img/ads/banner-1.svg') }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            @error('image_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">URL de destino (click)</label>
            <input type="text" name="target_url" value="{{ old('target_url', $ad->target_url) }}" placeholder="/productos o https://..."
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            @error('target_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            <p class="text-[11px] text-gray-400 mt-1">Ruta interna (/productos) o URL http(s). Los clicks se cuentan vía /ads/click/{id}.</p>
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Snippet HTML del proveedor (tipo html)</label>
            <textarea name="html" rows="5" placeholder="<!-- pega aquí el script de Adsterra/Monetag -->"
                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono">{{ old('html', $ad->html) }}</textarea>
            @error('html') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Activo desde</label>
            <input type="date" name="starts_at" value="{{ old('starts_at', $ad->starts_at?->toDateString()) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            @error('starts_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Activo hasta</label>
            <input type="date" name="ends_at" value="{{ old('ends_at', $ad->ends_at?->toDateString()) }}"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            @error('ends_at') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="md:col-span-2">
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $ad->is_active ?? true))>
                <span>Anuncio activo (visible en la web)</span>
            </label>
        </div>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button type="submit" class="bg-ink text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-gray-800">
            {{ $creating ? 'Crear anuncio' : 'Guardar cambios' }}
        </button>
        <a href="{{ route('admin.ads.index') }}" class="text-sm text-gray-500 hover:text-black underline">Cancelar</a>
    </div>
</form>
@endsection
