@extends('admin.layouts.app')

@section('title', 'Dashboard — Admin')
@section('heading', 'Dashboard')

@section('content')
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Productos</p>
        <p class="text-3xl font-black mt-1">{{ $totalProducts }}</p>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Pedidos</p>
        <p class="text-3xl font-black mt-1">{{ $totalOrders }}</p>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Clientes</p>
        <p class="text-3xl font-black mt-1">{{ $totalUsers }}</p>
    </div>
    <div class="bg-brand text-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs uppercase tracking-wider opacity-80">Ingresos</p>
        <p class="text-3xl font-black mt-1">{{ number_format($revenue, 2, ',', '.') }} €</p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-bold">Últimos pedidos</h2>
        <a href="#" class="text-sm text-gray-500 hover:text-ink">Ver todos</a>
    </div>

    @if($recentOrders->isEmpty())
        <p class="p-8 text-sm text-gray-500 text-center">Aún no hay pedidos.</p>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Pedido</th>
                    <th class="px-5 py-3 text-left">Cliente</th>
                    <th class="px-5 py-3 text-left">Estado</th>
                    <th class="px-5 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentOrders as $order)
                    <tr class="border-t border-gray-100">
                        <td class="px-5 py-3 font-mono">#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-5 py-3">{{ $order->customer_name }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">{{ $order->status }}</span>
                        </td>
                        <td class="px-5 py-3 text-right font-bold">{{ number_format($order->total, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection