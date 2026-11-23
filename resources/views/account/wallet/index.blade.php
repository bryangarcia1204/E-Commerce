@extends('account.layouts.app')
@section('title', 'Mi billetera MODA')

@section('content')
<h1 class="text-2xl font-black mb-6">Billetera MODA</h1>

{{-- Saldo --}}
<div class="bg-gradient-to-br from-ink to-gray-800 text-white rounded-3xl p-6 mb-6">
    <p class="text-xs uppercase tracking-widest opacity-70">Saldo disponible</p>
    <p class="text-4xl font-black mt-2">
        {{ number_format($balance / 100, 2, ',', '.') }} €
    </p>
    <p class="text-xs opacity-70 mt-3">
        Crédito interno utilizable en productos MODA. No reembolsable en efectivo.
    </p>
</div>

{{-- Movimientos --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-bold">Movimientos</h2>
    </div>

    @if($entries->isEmpty())
        <p class="p-8 text-sm text-gray-500 text-center">
            Sin movimientos todavía.
        </p>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Fecha</th>
                    <th class="px-5 py-3 text-left">Motivo</th>
                    <th class="px-5 py-3 text-left">Referencia</th>
                    <th class="px-5 py-3 text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entries as $entry)
                    <tr class="border-t border-gray-100">
                        <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                            {{ $entry->created_at?->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-5 py-3">{{ $entry->reason }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500 font-mono">
                            @if($entry->reference_type)
                                {{ class_basename($entry->reference_type) }} #{{ $entry->reference_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right font-bold {{ $entry->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $entry->amount >= 0 ? '+' : '' }}{{ number_format($entry->amount / 100, 2, ',', '.') }} €
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4">{{ $entries->links() }}</div>
    @endif
</div>
@endsection