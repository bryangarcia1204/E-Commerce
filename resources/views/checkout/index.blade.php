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
                $__chargeFmt = number_format($chargeable, 2, ',', '.');
                $__cardMethods = $savedMethods->where('type', 'card');
            @endphp

            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50 space-y-3 text-sm" x-data="{ method: '{{ old('payment_method', 'card') }}', useSaved: {{ $savedMethods->where('type', 'card')->isNotEmpty() ? 'true' : 'false' }} }">
                <p class="font-semibold text-gray-900">Método de pago</p>
                @if($chargeable > 0)
                    <p class="text-xs text-gray-500 -mt-1">
                        Se cobra <strong>{{ $__chargeFmt }} €</strong> (productos propios + envío).
                        @if($hasDropshipping) Los productos de tiendas asociadas se pagan en su tienda original. @endif
                    </p>
                @endif

                {{-- Tarjeta: Stripe real o sandbox --}}
                <label class="flex items-center gap-3 cursor-pointer border rounded-lg p-3 bg-white" :class="method === 'card' ? 'border-black' : 'border-gray-200'">
                    <input type="radio" name="payment_method" value="card" x-model="method"
                           @checked(old('payment_method', 'card') === 'card')>
                    <span class="font-medium">Tarjeta 💳</span>
                    <span class="text-gray-500">— {{ $isSandbox ? 'Entorno de prueba (sin cargo real)' : 'Pago seguro con Stripe' }}</span>
                </label>

                <div x-show="method === 'card'" x-cloak class="space-y-3 pl-1 border-l-2 border-gray-200 ml-3 pt-1">
                    {{-- Métodos guardados --}}
                    @forelse($__cardMethods as $pm)
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="radio" name="saved_payment_method_id" value="{{ $pm->id }}"
                                   @checked(old('saved_payment_method_id') == $pm->id)
                                   @change="useSaved = true">
                            <span>{{ $pm->label }} {{ $pm->is_default ? '· predeterminada' : '' }}</span>
                        </label>
                    @empty
                        <p class="text-xs text-gray-500">No tienes tarjetas guardadas.</p>
                    @endforelse

                    {{-- Tarjeta nueva --}}
                    @if($__cardMethods->isNotEmpty())
                        <label class="flex items-center gap-3 cursor-pointer text-xs text-gray-600">
                            <input type="radio" name="saved_payment_method_id" value="" @change="useSaved = false">
                            <span>Usar otra tarjeta…</span>
                        </label>
                    @endif

                    <div x-show="! useSaved" class="space-y-3 pt-1">
                        <div>
                            <label class="block text-xs font-semibold mb-1">Titular</label>
                            <input type="text" name="card_holder" autocomplete="cc-name" value="{{ old('card_holder') }}" placeholder="Nombre como aparece en la tarjeta"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:border-black focus:ring-0">
                            @error('card_holder') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1">Número de tarjeta</label>
                            <input type="text" name="card_number" inputmode="numeric" autocomplete="cc-number" value="{{ old('card_number') }}" placeholder="4242 4242 4242 4242"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:border-black focus:ring-0">
                            @error('card_number') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            @if($isSandbox)
                                <p class="text-[11px] text-gray-400 mt-1">Prueba: 4242 4242 4242 4242 (OK) · 4000 0000 0000 0002 (rechazada) · 4000 0000 0000 9995 (sin fondos)</p>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1">Caducidad</label>
                                <div class="flex gap-2">
                                    <input type="number" name="card_exp_month" min="1" max="12" placeholder="MM" value="{{ old('card_exp_month') }}"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:border-black focus:ring-0">
                                    <input type="number" name="card_exp_year" min="24" max="2100" placeholder="AA" value="{{ old('card_exp_year') }}"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:border-black focus:ring-0">
                                </div>
                                @error('card_exp_month') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                @error('card_exp_year') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">CVC</label>
                                <input type="password" inputmode="numeric" name="card_cvc" maxlength="4" placeholder="123" value="{{ old('card_cvc') }}"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2.5 focus:border-black focus:ring-0">
                                @error('card_cvc') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                            <input type="checkbox" name="save_card" value="1">
                            <span>Guardar esta tarjeta para próximas compras</span>
                        </label>
                    </div>
                </div>

                {{-- Billetera --}}
                <label class="flex items-center gap-3 cursor-pointer {{ $__wallet < $chargeable * 100 ? 'opacity-50' : '' }}">
                    <input type="radio" name="payment_method" value="wallet" x-model="method"
                        {{ $__wallet < $chargeable * 100 ? 'disabled' : '' }}>
                    <span>
                        <span class="font-medium">Billetera MODA</span>
                        <span class="text-gray-500">— Saldo: {{ $__walletFmt }} €</span>
                        @if($__wallet < $chargeable * 100)
                            <span class="text-red-500 text-xs block">Saldo insuficiente para {{ $__chargeFmt }} €</span>
                        @endif
                    </span>
                </label>

                {{-- Contra reembolso --}}
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="radio" name="payment_method" value="cod" x-model="method">
                    <span>
                        <span class="font-medium">Contra reembolso</span>
                        <span class="text-gray-500">— Se paga al recibir el pedido</span>
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


