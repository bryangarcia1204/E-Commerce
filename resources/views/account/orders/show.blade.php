@extends('account.layouts.app')
@section('title', 'Pedido #' . $order->id . ' — MODA')

@section('content')
<a href="{{ route('account.orders.index') }}" class="text-sm text-gray-500 hover:text-ink">← Volver a mis pedidos</a>

<div class="bg-white rounded-2xl shadow-sm p-6 mt-4 space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs text-gray-500 uppercase">Pedido</p>
            <p class="text-xl font-black font-mono">#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</p>
            <p class="text-sm text-gray-500">{{ $order->created_at?->format('d/m/Y H:i') }}</p>
        </div>
        <div class="text-right">
            <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">{{ $order->status }}</span>
            <p class="text-2xl font-black mt-2">{{ number_format($order->total, 2, ',', '.') }} €</p>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-4 text-sm">
        <div class="border border-gray-100 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase mb-1">Envío</p>
            <p class="font-semibold">{{ $order->customer_name }}</p>
            <p class="text-gray-600">{{ $order->customer_email }}</p>
            @if($order->customer_phone)
                <p class="text-gray-600">{{ $order->customer_phone }}</p>
            @endif
            <p class="text-gray-600 mt-1">{{ $order->shipping_address }}</p>
        </div>
        <div class="border border-gray-100 rounded-lg p-4">
            <p class="text-xs text-gray-500 uppercase mb-1">Resumen</p>
            <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span>{{ number_format($order->subtotal, 2, ',', '.') }} €</span></div>
            <div class="flex justify-between"><span class="text-gray-600">Envío</span><span>{{ $order->shipping <= 0 ? 'Gratis' : number_format($order->shipping, 2, ',', '.') . ' €' }}</span></div>
            <div class="flex justify-between font-bold border-t border-gray-100 pt-2 mt-2"><span>Total</span><span>{{ number_format($order->total, 2, ',', '.') }} €</span></div>
        </div>
    </div>

    <div>
        <p class="text-xs text-gray-500 uppercase mb-2">Productos</p>
        <div class="divide-y divide-gray-100">
            @foreach($order->items as $item)
                <div class="flex justify-between py-2 text-sm">
                    <span>{{ $item->quantity }}× {{ $item->product_name }}</span>
                    <span class="font-medium">{{ number_format($item->total, 2, ',', '.') }} €</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection