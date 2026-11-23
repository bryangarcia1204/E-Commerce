@extends('account.layouts.app')
@section('title', 'Mi cuenta — MODA')

@section('content')
<h1 class="text-2xl font-black mb-6">Hola, {{ $user->name }}</h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Pedidos</p>
        <p class="text-3xl font-black mt-1">{{ $ordersCount }}</p>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Pendientes</p>
        <p class="text-3xl font-black mt-1">{{ $pendingOrders }}</p>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm col-span-2 md:col-span-2">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Total gastado</p>
        <p class="text-3xl font-black mt-1">{{ number_format($ordersTotal, 2, ',', '.') }} €</p>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold">Último pedido</h2>
        <a href="{{ route('account.orders.index') }}" class="text-sm text-brand hover:underline">
            Ver todos →
        </a>
    </div>

    @if($lastOrder)
        <div class="flex items-center justify-between text-sm">
            <div>
                <p class="font-semibold">#{{ str_pad($lastOrder->id, 6, '0', STR_PAD_LEFT) }}</p>
                <p class="text-xs text-gray-500">{{ $lastOrder->created_at?->format('d/m/Y H:i') }}</p>
            </div>
            <div class="text-right">
                <p class="font-bold">{{ number_format($lastOrder->total, 2, ',', '.') }} €</p>
                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">{{ $lastOrder->status }}</span>
            </div>
        </div>
        <a href="{{ route('account.orders.show', $lastOrder) }}"
           class="inline-block mt-4 text-sm text-brand hover:underline">
            Ver detalle
        </a>
    @else
        <p class="text-sm text-gray-500">Aún no has realizado pedidos.</p>
        <a href="{{ route('products.index') }}"
           class="inline-block mt-4 bg-ink text-white px-4 py-2 rounded-full text-sm font-semibold">
            Empezar a comprar
        </a>
    @endif
</div>
@endsection