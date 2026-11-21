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

    {{-- Productos de tiendas asociadas: el usuario completa la compra en la
         tienda original vía nuestro enlace de afiliado. --}}
    @php
        $dropshippingItems = $order->items->filter(fn ($i) => $i->product && $i->product->is_dropshipping);
    @endphp
    @if($dropshippingItems->isNotEmpty())
        <div class="max-w-md mx-auto mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-left">
            <p class="font-bold text-amber-900 mb-1">🛍️ Completa tu compra en la tienda asociada</p>
            <p class="text-xs text-amber-800 mb-4">Estos productos se compran directamente en la tienda original. Pulsa cada botón para finalizar la compra; la tienda se encarga del pago y del envío.</p>
            <div class="space-y-3">
                @foreach($dropshippingItems as $item)
                    <div class="flex items-center justify-between gap-3 bg-white rounded-lg p-3">
                        <div class="text-xs">
                            <p class="font-semibold text-gray-900">{{ $item->quantity }}× {{ $item->product_name }}</p>
                            <p class="text-gray-500 uppercase">{{ $item->product->external_store }} · {{ number_format((float) $item->total, 2, ',', '.') }} €</p>
                        </div>
                        <a href="{{ route('affiliate.redirect', $item->product) }}"
                           target="_blank" rel="noopener noreferrer nofollow sponsored"
                           class="inline-block bg-black text-white text-xs font-semibold px-4 py-2 rounded-full hover:bg-gray-800 whitespace-nowrap">
                            Comprar en {{ ucfirst($item->product->external_store) }} →
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

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