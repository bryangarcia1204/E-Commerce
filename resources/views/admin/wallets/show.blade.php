@extends('admin.layouts.app')
@section('title', 'Billetera de ' . $wallet->user->name)
@section('heading', 'Billetera de ' . $wallet->user->name)

@section('content')
<a href="{{ route('admin.wallets.index') }}" class="text-sm text-gray-500 hover:text-ink">← Volver</a>

<div class="grid md:grid-cols-3 gap-4 mt-4 mb-6">
    <div class="bg-white rounded-2xl p-5 shadow-sm md:col-span-2">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Saldo actual</p>
        <p class="text-3xl font-black mt-1">{{ number_format($balance / 100, 2, ',', '.') }} €</p>
        <p class="text-xs text-gray-500 mt-1">{{ $wallet->user->email }}</p>
    </div>

    <form method="POST" action="{{ route('admin.wallets.adjust', $wallet) }}"
          class="bg-white rounded-2xl p-5 shadow-sm space-y-3">
        @csrf
        <p class="font-bold text-sm">Ajuste manual</p>
        <input type="number" step="0.01" name="amount" placeholder="Ej. 10.00 o -5.00" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <input type="text" name="reason" placeholder="Motivo (auditoría)" required
               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <button class="w-full bg-ink text-white rounded-lg py-2 text-sm font-semibold">Aplicar</button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-bold">Movimientos ({{ $entries->total() }})</h2>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
                <th class="px-5 py-3 text-left">ID</th>
                <th class="px-5 py-3 text-left">Fecha</th>
                <th class="px-5 py-3 text-left">Motivo</th>
                <th class="px-5 py-3 text-left">Referencia</th>
                <th class="px-5 py-3 text-right">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $e)
                <tr class="border-t border-gray-100">
                    <td class="px-5 py-3 text-xs font-mono">{{ $e->id }}</td>
                    <td class="px-5 py-3 text-xs text-gray-500">{{ $e->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-5 py-3">{{ $e->reason }}</td>
                    <td class="px-5 py-3 text-xs text-gray-500">
                        @if($e->reference_type)
                            {{ class_basename($e->reference_type) }} #{{ $e->reference_id }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right font-bold {{ $e->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $e->amount >= 0 ? '+' : '' }}{{ number_format($e->amount / 100, 2, ',', '.') }} €
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-4">{{ $entries->links() }}</div>
</div>
@endsection