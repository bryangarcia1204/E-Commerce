@extends('layouts.app')

@section('title', 'Pedido ' . $order->number . ' — MODA')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10">
    <a href="{{ route('orders.index') }}" class="text-sm text-gray-500 hover:text-black underline">← Mis pedidos</a>

    <h1 class="text-2xl md:text-3xl font-black mt-4 mb-2">Pedido {{ $order->number }}</h1>
    <div class="flex items-center gap-3 mb-8">
        @include('partials.order-status-badge', ['status' => $order->status])
        <span class="text-xs text-gray-500">{{ $order->created_at->format('d/m/Y H:i') }}</span>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Producto</th>
                    <th class="px-5 py-3 text-right">Precio</th>
                    <th class="px-5 py-3 text-right">Uds.</th>
                    <th class="px-5 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr class="border-t border-gray-100">
                        <td class="px-5 py-3">{{ $item->product_name }}</td>
                        <td class="px-5 py-3 text-right">{{ number_format((float) $item->price, 2, ',', '.') }} €</td>
                        <td class="px-5 py-3 text-right">{{ $item->quantity }}</td>
                        <td class="px-5 py-3 text-right font-medium">{{ number_format((float) $item->total, 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="border-t border-gray-200 p-5 space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-gray-600">Subtotal</span>
                 <span>{{ number_format((float) $order->subtotal, 2, ',', '.') }} €</span></div>
            <div class="flex justify-between"><span class="text-gray-600">Envío</span>
                 <span>@if((float) $order->shipping > 0) {{ number_format((float) $order->shipping, 2, ',', '.') }} € @else Gratis @endif</span></div>
            <div class="flex justify-between font-bold text-base border-t border-gray-200 pt-2">
                 <span>Total</span><span>{{ number_format((float) $order->total, 2, ',', '.') }} €</span></div>
        </div>
    </div>

    <div class="bg-gray-50 rounded-2xl p-5 text-sm">
        <h2 class="font-bold mb-2">Dirección de envío</h2>
        <p class="text-gray-700 whitespace-pre-line">{{ $order->shipping_address }}</p>
    </div>
</div>
@endsection
