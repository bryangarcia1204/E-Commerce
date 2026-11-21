@extends('admin.layouts.app')

@section('title', 'Pedidos — Admin')
@section('heading', 'Pedidos')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <form method="GET" class="flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por nº, cliente o email..."
            class="border border-gray-200 rounded-lg px-4 py-2 text-sm focus:border-ink focus:ring-0 w-72">
        <select name="status" onchange="this.form.submit()"
            class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-ink focus:ring-0">
            <option value="">Todos los estados</option>
            @foreach($statuses as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <button type="submit" class="bg-ink text-white px-4 py-2 rounded-lg text-sm font-semibold">Buscar</button>
    </form>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    @if($orders->isEmpty())
        <p class="p-8 text-sm text-gray-500 text-center">No hay pedidos.</p>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Pedido</th>
                    <th class="px-5 py-3 text-left">Cliente</th>
                    <th class="px-5 py-3 text-left">Fecha</th>
                    <th class="px-5 py-3 text-left">Estado</th>
                    <th class="px-5 py-3 text-right">Total</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono">{{ $order->number }}</td>
                        <td class="px-5 py-3">
                            <div class="font-medium">{{ $order->customer_name }}</div>
                            <div class="text-xs text-gray-500">{{ $order->customer_email }}</div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-3">
                            @include('partials.order-status-badge', ['status' => $order->status])
                        </td>
                        <td class="px-5 py-3 text-right font-bold">{{ number_format((float) $order->total, 2, ',', '.') }} €</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.orders.show', $order) }}"
                               class="text-xs text-blue-600 hover:underline">Ver</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
