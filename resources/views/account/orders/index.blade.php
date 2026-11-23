@extends('account.layouts.app')
@section('title', 'Mis pedidos — MODA')

@section('content')
<h1 class="text-2xl font-black mb-6">Mis pedidos</h1>

@if($orders->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm p-10 text-center">
        <p class="text-4xl mb-3">📦</p>
        <p class="text-gray-500 mb-4">Todavía no tienes pedidos.</p>
        <a href="{{ route('products.index') }}"
           class="inline-block bg-ink text-white px-6 py-3 rounded-full text-sm font-semibold">
            Explorar productos
        </a>
    </div>
@else
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Pedido</th>
                    <th class="px-5 py-3 text-left">Fecha</th>
                    <th class="px-5 py-3 text-center">Productos</th>
                    <th class="px-5 py-3 text-left">Estado</th>
                    <th class="px-5 py-3 text-right">Total</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono">
                            #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $order->created_at?->format('d/m/Y') }}</td>
                        <td class="px-5 py-3 text-center">{{ $order->items_count }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">{{ $order->status }}</span>
                        </td>
                        <td class="px-5 py-3 text-right font-bold">{{ number_format($order->total, 2, ',', '.') }} €</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('account.orders.show', $order) }}"
                               class="text-sm text-brand hover:underline">Ver</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4">{{ $orders->links() }}</div>
    </div>
@endif
@endsection