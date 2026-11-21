@extends('layouts.app')

@section('title', 'Pedido confirmado — MODA')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-16 text-center">
    <div class="text-6xl mb-4">✅</div>
    <h1 class="text-3xl font-black mb-2">¡Gracias por tu compra!</h1>
    <p class="text-gray-600 mb-8">
        Pedido <span class="font-bold">#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</span>
        creado correctamente.
    </p>

    <div class="bg-gray-50 rounded-2xl p-6 text-left space-y-3 max-w-md mx-auto">
        <div class="flex justify-between text-sm">
            <span class="text-gray-600">Estado</span>
            <span class="font-semibold uppercase text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">
                {{ $order->status }}
            </span>
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
            <span>{{ number_format($order->total, 2, ',', '.') }} €</span>
        </div>
    </div>

    <a href="{{ route('home') }}"
       class="inline-block mt-8 bg-black text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-gray-800">
        Volver al inicio
    </a>
</div>
@endsection