@extends('admin.layouts.app')

@section('title', 'Liquidaciones — Admin')
@section('heading', 'Liquidaciones dropshipping')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <form method="GET" class="flex gap-2">
        <select name="type" onchange="this.form.submit()"
            class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-ink focus:ring-0">
            <option value="">Todos los tipos</option>
            @foreach($types as $slug => $label)
                <option value="{{ $slug }}" @selected(request('type') === $slug)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status" onchange="this.form.submit()"
            class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-ink focus:ring-0">
            <option value="">Todos los estados</option>
            @foreach($statuses as $slug => $label)
                <option value="{{ $slug }}" @selected(request('status') === $slug)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <p class="text-xs text-gray-500">Las liquidaciones reflejan el flujo de caja pendiente. No exponen secretos de usuario (tokens, billeteras, contraseñas).</p>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    @if($settlements->isEmpty())
        <p class="p-8 text-sm text-gray-500 text-center">No hay asientos. Los pedidos generarán liquidaciones automáticamente al confirmarse.</p>
    @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Pedido</th>
                    <th class="px-5 py-3 text-left">Tipo</th>
                    <th class="px-5 py-3 text-left">Descripción</th>
                    <th class="px-5 py-3 text-left">Estado</th>
                    <th class="px-5 py-3 text-right">Importe</th>
                    <th class="px-5 py-3 text-left">Fecha</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($settlements as $s)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono">{{ $s->order?->number ?? '#'.$s->order_id }}</td>
                        <td class="px-5 py-3">{{ $types[$s->type] ?? $s->type }}</td>
                        <td class="px-5 py-3 text-gray-600 text-xs">{{ $s->description ?? '—' }}</td>
                        <td class="px-5 py-3">
                            @php
                                $badge = match($s->status) {
                                    'pending' => 'bg-amber-100 text-amber-800',
                                    'settled' => 'bg-green-100 text-green-800',
                                    'disputed' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <span class="inline-block text-[10px] uppercase px-2 py-0.5 rounded {{ $badge }}">{{ $statuses[$s->status] ?? $s->status }}</span>
                        </td>
                        <td class="px-5 py-3 text-right font-bold">{{ number_format((float) $s->amount, 2, ',', '.') }} €</td>
                        <td class="px-5 py-3 text-gray-600 text-xs">{{ $s->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-3 text-right">
                            @if($s->type === 'commission_owed_by_store' && $s->status === 'pending')
                                <form method="POST" action="{{ route('admin.settlements.settle', $s) }}">
                                    @csrf
                                    <input type="text" name="external_ref" placeholder="ref conversión" class="border border-gray-200 rounded px-2 py-1 text-xs w-28">
                                    <button type="submit" class="text-xs text-blue-600 hover:underline ml-1">Liquidar</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <div class="p-4">{{ $settlements->links() }}</div>
    @endif
</div>
@endsection
