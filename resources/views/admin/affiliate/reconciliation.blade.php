@extends('admin.layouts.app')
@section('title', 'Conciliación — Admin')
@section('heading', 'Conciliación de comisiones')

@section('content')

<form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Desde</label>
        <input type="date" name="from" value="{{ $from }}" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Hasta</label>
        <input type="date" name="to" value="{{ $to }}" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Red</label>
        <select name="network" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="">Todas</option>
            <option value="Amazon"     @selected($network === 'Amazon')>Amazon</option>
            <option value="AliExpress" @selected($network === 'AliExpress')>AliExpress</option>
            <option value="Shein"      @selected($network === 'Shein')>Shein</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Estado</label>
        <select name="status" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="">Todos</option>
            <option value="pending"   @selected($status === 'pending')>Pendiente</option>
            <option value="converted" @selected($status === 'converted')>Convertido</option>
            <option value="paid"      @selected($status === 'paid')>Pagado</option>
            <option value="rejected"  @selected($status === 'rejected')>Rechazado</option>
        </select>
    </div>
    <button class="bg-ink text-white px-4 py-2 rounded-lg text-sm font-semibold">Filtrar</button>
</form>

{{-- Totales por estado --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @foreach($totals as $t)
        <div class="bg-white rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-gray-500 uppercase tracking-wider">{{ ucfirst($t->status) }}</p>
            <p class="text-2xl font-black mt-1">{{ $t->cnt }}</p>
            <p class="text-xs text-gray-500">{{ number_format($t->sum ?? 0, 2, ',', '.') }} €</p>
        </div>
    @endforeach
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Fecha</th>
                    <th class="px-4 py-3 text-left">Red</th>
                    <th class="px-4 py-3 text-left">Producto</th>
                    <th class="px-4 py-3 text-left">Sub-ID</th>
                    <th class="px-4 py-3 text-left">Orden ext.</th>
                    <th class="px-4 py-3 text-right">Comisión</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($clicks as $c)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                            {{ $c->created_at?->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-xs">{{ $c->network ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs truncate max-w-xs">{{ $c->product?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs font-mono truncate max-w-[100px]">{{ $c->sub_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs font-mono">{{ $c->external_order_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-medium">
                            {{ number_format($c->commission_amount, 2, ',', '.') }} €
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $colors = [
                                    'pending'   => 'bg-gray-100 text-gray-700',
                                    'converted' => 'bg-yellow-100 text-yellow-800',
                                    'paid'      => 'bg-green-100 text-green-800',
                                    'rejected'  => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="text-xs px-2 py-1 rounded {{ $colors[$c->status] ?? 'bg-gray-100' }}">
                                {{ $c->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @if($c->status === 'converted')
                                <form method="POST" action="{{ route('admin.affiliate.markPaid', $c) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button class="text-xs text-green-600 hover:underline mr-2">Marcar pagado</button>
                                </form>
                                <form method="POST" action="{{ route('admin.affiliate.reject', $c) }}" class="inline"
                                      onsubmit="return confirm('¿Rechazar este clic?')">
                                    @csrf @method('PATCH')
                                    <button class="text-xs text-red-600 hover:underline">Rechazar</button>
                                </form>
                            @elseif($c->status === 'pending')
                                <span class="text-xs text-gray-400">Esperando postback</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Sin clics en el rango.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $clicks->links() }}</div>
</div>
@endsection