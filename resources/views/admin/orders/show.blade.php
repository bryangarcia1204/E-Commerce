@extends('admin.layouts.app')

@section('title', 'Pedido ' . $order->number . ' — Admin')
@section('heading', 'Pedido ' . $order->number)

@section('content')
<div class="grid md:grid-cols-3 gap-6">
    {{-- Detalle --}}
    <div class="md:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold">Estado del pedido</h2>
                @include('partials.order-status-badge', ['status' => $order->status])
            </div>

            <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="flex items-center gap-3">
                @csrf
                @method('PATCH')
                <select name="status" class="border border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-ink focus:ring-0">
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected($order->status === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-ink text-white px-4 py-2 rounded-lg text-sm font-semibold">
                    Actualizar estado
                </button>
            </form>
        </div>

        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-bold">Artículos</h2>
            </div>
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
                <tfoot class="border-t border-gray-200">
                    <tr><td colspan="3" class="px-5 py-3 text-right text-gray-600">Subtotal</td>
                        <td class="px-5 py-3 text-right">{{ number_format((float) $order->subtotal, 2, ',', '.') }} €</td></tr>
                    <tr><td colspan="3" class="px-5 py-3 text-right text-gray-600">Envío</td>
                        <td class="px-5 py-3 text-right">
                            @if((float) $order->shipping > 0)
                                {{ number_format((float) $order->shipping, 2, ',', '.') }} €
                            @else
                                Gratis
                            @endif
                        </td></tr>
                    <tr><td colspan="3" class="px-5 py-3 text-right font-bold">Total</td>
                        <td class="px-5 py-3 text-right font-bold">{{ number_format((float) $order->total, 2, ',', '.') }} €</td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Datos del cliente --}}
    <aside class="space-y-6">
        <div class="bg-white rounded-2xl shadow-sm p-6">
            <h2 class="font-bold mb-3">Cliente</h2>
            <dl class="text-sm space-y-2">
                <div><dt class="text-gray-500">Nombre</dt><dd class="font-medium">{{ $order->customer_name }}</dd></div>
                <div><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ $order->customer_email }}</dd></div>
                @if($order->customer_phone)
                    <div><dt class="text-gray-500">Teléfono</dt><dd>{{ $order->customer_phone }}</dd></div>
                @endif
                @if($order->user_id)
                    <div><dt class="text-gray-500">Usuario</dt><dd>#{{ $order->user_id }}</dd></div>
                @endif
                <div><dt class="text-gray-500">Fecha</dt><dd>{{ $order->created_at->format('d/m/Y H:i') }}</dd></div>
            </dl>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-6">
            <h2 class="font-bold mb-3">Dirección de envío</h2>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $order->shipping_address }}</p>
        </div>
    </aside>
</div>
@endsection
