@extends('layouts.app')

@section('title', 'Checkout — MODA')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-10">
    <h1 class="text-2xl md:text-3xl font-black mb-8">Finalizar compra</h1>

    @if($hasDropshipping)
        <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg text-sm mb-6">
            <p class="font-semibold mb-1">🛍️ Tu carrito incluye productos de tiendas asociadas (dropshipping)</p>
            <p>Tu pedido se registra aquí, pero los productos marcados se compran directamente en la tienda original (Shein, AliExpress, etc.) a través de nuestro enlace de afiliado. La tienda se encarga del pago y del envío; nosotros canalizamos la venta.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('checkout.store') }}" class="grid md:grid-cols-3 gap-8">
        @csrf

        {{-- Datos pre-poblados desde el perfil del usuario --}}
        <div class="md:col-span-2 space-y-5">
            <div>
                <label class="block text-sm font-semibold mb-1">Nombre completo *</label>
                <input type="text" name="customer_name" value="{{ $defaults['customer_name'] }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-black focus:ring-0">
                @error('customer_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-1">Email *</label>
                    <input type="email" name="customer_email" value="{{ $defaults['customer_email'] }}" required
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-black focus:ring-0">
                    @error('customer_email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Teléfono</label>
                    <input type="text" name="customer_phone" value="{{ $defaults['customer_phone'] }}"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-black focus:ring-0">
                    @error('customer_phone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Dirección de envío *</label>
                <textarea name="shipping_address" rows="3" required
                          class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:border-black focus:ring-0">{{ $defaults['shipping_address'] }}</textarea>
                @error('shipping_address') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Método de pago --}}
            @php
                $__wallet = app(\App\Services\WalletService::class)->balance(auth()->user());
                $__walletFmt = number_format($__wallet / 100, 2, ',', '.');
            @endphp

            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-3 text-sm">
                <p class="font-semibold text-gray-900">Método de pago</p>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="radio" name="payment_method" value="wallet" required
                        @checked(old('payment_method') === 'wallet')
                        {{ $__wallet < $total * 100 ? 'disabled' : '' }}>
                    <span>
                        <span class="font-medium">Billetera MODA</span>
                        <span class="text-gray-500">— Saldo: {{ $__walletFmt }} €</span>
                        @if($__wallet < $total * 100)
                            <span class="text-red-500 text-xs block">Saldo insuficiente</span>
                        @endif
                    </span>
                </label>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="radio" name="payment_method" value="external" required
                        @checked(old('payment_method', 'external') === 'external')>
                    <span>
                        <span class="font-medium">Pago externo (demo)</span>
                        <span class="text-gray-500">— El pedido queda pendiente de pago</span>
                    </span>
                </label>

                @error('payment_method') <p class="text-red-500 text-xs">{{ $message }}</p> @enderror
            </div>

        {{-- Resumen --}}
        <aside class="md:col-span-1">
            <div class="bg-gray-50 rounded-2xl p-6 space-y-4 sticky top-24">
                <h2 class="font-black text-lg">Tu pedido</h2>

                <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                    @foreach($items as $item)
                        @php $p = $products->get($item['id']); @endphp
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-700">
                                {{ $item['quantity'] }}× {{ $item['name'] }}
                                @if($p && $p->is_dropshipping)
                                    <span class="ml-1 inline-block text-[10px] uppercase bg-amber-100 text-amber-800 px-1 rounded">{{ $p->external_store }}</span>
                                @endif
                            </span>
                            <span class="font-medium">{{ number_format($item['price'] * $item['quantity'], 2, ',', '.') }} €</span>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-gray-200 pt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600">Subtotal</span><span>{{ number_format($subtotal, 2, ',', '.') }} €</span></div>
                    <div class="flex justify-between"><span class="text-gray-600">Envío</span><span>{{ $shipping <= 0 ? 'Gratis' : number_format($shipping, 2, ',', '.') . ' €' }}</span></div>
                </div>

                <div class="border-t border-gray-200 pt-3 flex justify-between font-bold text-lg">
                    <span>Total</span>
                    <span>{{ number_format($total, 2, ',', '.') }} €</span>
                </div>

                <button type="submit"
                        class="block w-full bg-black text-white text-center rounded-lg py-3 font-semibold hover:bg-gray-800 transition">
                    Confirmar pedido
                </button>

                <a href="{{ route('cart.index') }}" class="block text-center text-xs text-gray-500 hover:text-black underline">
                    ← Volver al carrito
                </a>
            </div>
        </aside>
    </form>
</div>
@endsection


