@extends('layouts.app')

@section('title', 'Carrito — MODA')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-10">
    <h1 class="text-2xl md:text-3xl font-black mb-8">Tu carrito</h1>

    @if(empty($items))
        <div class="text-center py-20">
            <p class="text-4xl mb-3">🛒</p>
            <p class="text-gray-500 mb-4">Tu carrito está vacío.</p>
            <a href="{{ route('products.index') }}"
               class="inline-block bg-black text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-gray-800">
                Explorar productos
            </a>
        </div>
    @else
        <div class="grid md:grid-cols-3 gap-8">

            {{-- Lista de items --}}
            <div class="md:col-span-2 space-y-3">
                @foreach($items as $item)
                    <div class="flex gap-4 border border-gray-100 rounded-xl p-3 bg-white">
                        <a href="{{ route('products.show', $item['slug']) }}"
                           class="w-24 h-32 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                            <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}"
                                 class="w-full h-full object-cover">
                        </a>

                        <div class="flex-1 flex flex-col justify-between">
                            <div>
                                <h3 class="font-semibold text-sm">
                                    <a href="{{ route('products.show', $item['slug']) }}" class="hover:underline">
                                        {{ $item['name'] }}
                                    </a>
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ number_format($item['price'], 2, ',', '.') }} € / ud.
                                </p>
                            </div>

                            <div class="flex items-center justify-between mt-3">
                                {{-- Actualizar cantidad --}}
                                <form method="POST"
                                      action="{{ route('cart.update', $item['slug']) }}"
                                      class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" name="quantity" value="{{ $item['quantity'] - 1 }}"
                                            class="w-8 h-8 hover:bg-gray-50 flex items-center justify-center">−</button>
                                    <span class="w-10 h-8 flex items-center justify-center text-sm font-medium">
                                        {{ $item['quantity'] }}
                                    </span>
                                    <button type="submit" name="quantity" value="{{ $item['quantity'] + 1 }}"
                                            class="w-8 h-8 hover:bg-gray-50 flex items-center justify-center">+</button>
                                </form>

                                <div class="text-right">
                                    <p class="text-sm font-bold">
                                        {{ number_format($item['price'] * $item['quantity'], 2, ',', '.') }} €
                                    </p>
                                    <form method="POST" action="{{ route('cart.remove', $item['slug']) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-xs text-gray-400 hover:text-red-500 underline mt-1">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-between items-center pt-3">
                    <a href="{{ route('products.index') }}" class="text-sm text-gray-500 hover:text-black underline">
                        ← Seguir comprando
                    </a>
                    <form method="POST" action="{{ route('cart.clear') }}">
                        @csrf
                        <button type="submit" class="text-xs text-gray-400 hover:text-red-500 underline">
                            Vaciar carrito
                        </button>
                    </form>
                </div>
            </div>

            {{-- Resumen --}}
            <aside class="md:col-span-1">
                <div class="bg-gray-50 rounded-2xl p-6 space-y-4 sticky top-24">
                    <h2 class="font-black text-lg">Resumen</h2>

                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span>{{ number_format($subtotal, 2, ',', '.') }} €</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Envío</span>
                        <span>
                            @if($shipping <= 0)
                                <span class="text-green-600 font-semibold">Gratis</span>
                            @else
                                {{ number_format($shipping, 2, ',', '.') }} €
                            @endif
                        </span>
                    </div>

                    @if($subtotal < 50 && $subtotal > 0)
                        <p class="text-xs text-gray-500 bg-white border border-gray-200 rounded-lg p-2">
                            Añade {{ number_format(50 - $subtotal, 2, ',', '.') }} € más para envío gratis.
                        </p>
                    @endif

                    <div class="border-t border-gray-200 pt-3 flex justify-between font-bold text-lg">
                        <span>Total</span>
                        <span>{{ number_format($total, 2, ',', '.') }} €</span>
                    </div>

                    <a href="{{ route('checkout.index') }}"
                       class="block bg-black text-white text-center rounded-lg py-3 font-semibold hover:bg-gray-800 transition">
                        Ir a pagar
                    </a>
                </div>
            </aside>
        </div>
    @endif
</div>
@endsection