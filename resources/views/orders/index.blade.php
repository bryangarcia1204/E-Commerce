@extends('layouts.app')

@section('title', 'Mis pedidos — MODA')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-10">
    <h1 class="text-2xl md:text-3xl font-black mb-8">Mis pedidos</h1>

    @if($orders->isEmpty())
        <div class="text-center py-20">
            <p class="text-4xl mb-3">📦</p>
            <p class="text-gray-500 mb-4">Todavía no tienes pedidos.</p>
            <a href="{{ route('products.index') }}"
               class="inline-block bg-black text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-gray-800">
                Empezar a comprar
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($orders as $order)
                <a href="{{ route('orders.show', $order) }}"
                   class="block bg-white border border-gray-100 rounded-2xl p-5 hover:shadow-md transition">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <p class="font-mono text-sm text-gray-500">Pedido {{ $order->number }}</p>
                            <p class="font-bold">{{ $order->items->sum('quantity') }} artículos ·
                               {{ number_format((float) $order->total, 2, ',', '.') }} €</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            @include('partials.order-status-badge', ['status' => $order->status])
                            <span class="text-xs text-gray-400">Ver detalle →</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
