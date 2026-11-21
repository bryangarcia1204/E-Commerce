@extends('layouts.app')

@section('title', 'Pedido confirmado — MODA')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-16 text-center">
    <div class="text-6xl mb-4">✅</div>
    <h1 class="text-3xl font-black mb-2">¡Gracias por tu compra!</h1>
    <p class="text-gray-600 mb-8">
        Pedido <span class="font-bold">{{ $order->number }}</span>
        creado correctamente.
    </p>

    <div class="bg-gray-50 rounded-2xl p-6 text-left space-y-3 max-w-md mx-auto">
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Estado</span>
            @include('partials.order-status-badge', ['status' => $order->status])
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Email</span>
            <span>{{ $order->customer_email }}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Productos</span>
            <span>{{ $order->items->sum('quantity') }}</span>
        </div>
        <div class="flex justify-between text-sm font-bold border-t border-gray-200 pt-3">
            <span>Total</span>
            <span>{{ number_format((float) $order->total, 2, ',', '.') }} €</span>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-center gap-4 mt-8">
        <a href="{{ route('orders.show', $order) }}"
           class="inline-block bg-black text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-gray-800">
            Ver detalle del pedido
        </a>
        <a href="{{ route('orders.index') }}"
           class="inline-block text-sm text-gray-500 hover:text-black underline">
            Mis pedidos
        </a>
    </div>
</div>
@endsection