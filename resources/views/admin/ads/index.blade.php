@extends('admin.layouts.app')
@section('title', 'Anuncios — Admin')
@section('heading', 'Anuncios · posiciones y métricas')

@section('content')

{{-- Resumen por posición activa --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @forelse($byPosition as $row)
        <div class="bg-white rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 uppercase tracking-wider">
                {{ \App\Models\Ad::POSITIONS[$row->position] ?? $row->position }}
            </p>
            <p class="text-2xl font-black mt-1">{{ number_format($row->impressions ?? 0) }} <span class="text-xs font-normal text-gray-400">impresiones</span></p>
            <p class="text-xs text-gray-500 mt-1">
                {{ number_format($row->clicks ?? 0) }} clicks · {{ $row->total }} activo(s)
            </p>
        </div>
    @empty
        <div class="bg-white rounded-2xl p-5 shadow-sm md:col-span-4 text-sm text-gray-500">
            Sin anuncios activos. {{ config('services.ads.enabled') ? '' : 'Nota: ADS_ENABLED está desactivado en .env; actívalo para mostrar los anuncios.' }}
        </div>
    @endforelse
</div>

<div class="flex items-center justify-between mb-4">
    <h2 class="font-bold">Todos los anuncios</h2>
    <a href="{{ route('admin.ads.create') }}"
       class="bg-brand text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-brand-dark">
        + Nuevo anuncio
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Nombre</th>
                    <th class="px-5 py-3 text-left">Posición</th>
                    <th class="px-5 py-3 text-left">Tipo</th>
                    <th class="px-5 py-3 text-left">Vigencia</th>
                    <th class="px-5 py-3 text-right">Impresiones</th>
                    <th class="px-5 py-3 text-right">Clicks</th>
                    <th class="px-5 py-3 text-right">CTR</th>
                    <th class="px-5 py-3 text-center">Estado</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($ads as $ad)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-5 py-3 font-medium">{{ $ad->name }}</td>
                        <td class="px-5 py-3 text-xs">
                            <span class="bg-gray-100 rounded px-2 py-0.5">{{ $ad->position }}</span>
                            <span class="text-gray-400 ml-1">{{ \App\Models\Ad::POSITIONS[$ad->position] ?? '' }}</span>
                        </td>
                        <td class="px-5 py-3 text-xs">{{ \App\Models\Ad::TYPES[$ad->type] ?? $ad->type }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                            {{ $ad->starts_at?->format('d/m/Y') ?? '—' }} → {{ $ad->ends_at?->format('d/m/Y') ?? '∞' }}
                        </td>
                        <td class="px-5 py-3 text-right">{{ number_format($ad->impressions) }}</td>
                        <td class="px-5 py-3 text-right">{{ number_format($ad->clicks) }}</td>
                        <td class="px-5 py-3 text-right">{{ number_format($ad->ctr, 2, ',', '.') }}%</td>
                        <td class="px-5 py-3 text-center">
                            <span class="text-xs px-2 py-1 rounded {{ $ad->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ $ad->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.ads.edit', $ad) }}" class="text-xs text-gray-600 hover:underline mr-3">Editar</a>
                            <form method="POST" action="{{ route('admin.ads.toggle', $ad) }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="text-xs {{ $ad->is_active ? 'text-amber-600' : 'text-green-600' }} hover:underline mr-3">
                                    {{ $ad->is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.ads.destroy', $ad) }}" class="inline"
                                  onsubmit="return confirm('¿Eliminar este anuncio?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-5 py-8 text-center text-gray-500">Sin anuncios. Crea el primero.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $ads->links() }}</div>
</div>
@endsection
