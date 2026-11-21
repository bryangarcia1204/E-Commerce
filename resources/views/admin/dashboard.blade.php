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
        <p class="text-3xl font-black mt-1">{{ number_format((float) $revenue, 2, ',', '.') }} €</p>
    </div>
</div>

{{-- Finanzas dropshipping (agregado, sin secretos de usuarios) --}}
<div class="bg-white rounded-2xl shadow-sm p-6 mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold">Flujo de caja dropshipping</h2>
        <a href="{{ route('admin.settlements.index') }}" class="text-sm text-gray-500 hover:text-ink">Liquidaciones →</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
        <div class="rounded-lg bg-amber-50 p-3">
            <p class="text-xs text-amber-700 uppercase">Por cobrar (cliente)</p>
            <p class="font-black text-lg mt-1">{{ number_format((float) $pendingReceivable, 2, ',', '.') }} €</p>
        </div>
        <div class="rounded-lg bg-blue-50 p-3">
            <p class="text-xs text-blue-700 uppercase">Comisión por cobrar</p>
            <p class="font-black text-lg mt-1">{{ number_format((float) $pendingCommission, 2, ',', '.') }} €</p>
        </div>
        <div class="rounded-lg bg-red-50 p-3">
            <p class="text-xs text-red-700 uppercase">Por pagar (proveedor)</p>
            <p class="font-black text-lg mt-1">{{ number_format((float) $pendingPayable, 2, ',', '.') }} €</p>
        </div>
        <div class="rounded-lg bg-green-50 p-3">
            <p class="text-xs text-green-700 uppercase">Margen realizado</p>
            <p class="font-black text-lg mt-1">{{ number_format((float) $realizedMargin, 2, ',', '.') }} €</p>
        </div>
        <div class="rounded-lg bg-green-50 p-3">
            <p class="text-xs text-green-700 uppercase">Comisión realizada</p>
            <p class="font-black text-lg mt-1">{{ number_format((float) $realizedCommission, 2, ',', '.') }} €</p>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-bold">Últimos pedidos</h2>
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-gray-500 hover:text-ink">Ver todos →</a>
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
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono">
                            <a href="{{ route('admin.orders.show', $order) }}" class="hover:underline">{{ $order->number }}</a>
                        </td>
                        <td class="px-5 py-3">{{ $order->customer_name }}</td>
                        <td class="px-5 py-3">
                            @include('partials.order-status-badge', ['status' => $order->status])
                        </td>
                        <td class="px-5 py-3 text-right font-bold">{{ number_format((float) $order->total, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection