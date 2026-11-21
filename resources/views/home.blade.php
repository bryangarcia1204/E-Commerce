@extends('layouts.app')
@section('title', 'MODA — Moda moderna online')

@section('content')

{{-- HERO --}}
<section class="relative overflow-hidden bg-gradient-to-br from-rose-50 via-white to-amber-50">
    <div class="max-w-7xl mx-auto px-4 py-16 md:py-24 grid md:grid-cols-2 gap-10 items-center">
        <div class="space-y-6 fade-in">
            <span class="inline-flex items-center gap-2 text-xs font-bold tracking-widest uppercase text-brand bg-white px-3 py-1.5 rounded-full shadow-sm">
                <x-icon name="fire" class="w-3 h-3"/> Nueva colección
            </span>
            <h1 class="text-5xl md:text-7xl font-black leading-[0.95] tracking-tight">
                Renueva<br>tu estilo<br><span class="text-brand">esta temporada</span>
            </h1>
            <p class="text-gray-600 max-w-md text-lg">
                Descubre las últimas tendencias a precios increíbles.
                Envío gratis desde 50 € y devoluciones en 30 días.
            </p>
            <div class="flex flex-wrap gap-3">
                <x-button href="{{ route('products.index') }}">Ver productos</x-button>
                <x-button href="{{ route('products.index', ['sort' => 'newest']) }}" variant="secondary">Novedades</x-button>
            </div>
        </div>
        <div class="relative">
            <div class="aspect-square rounded-[2.5rem] overflow-hidden shadow-2xl">
                <img src="https://picsum.photos/seed/hero-moda/900/900" class="w-full h-full object-cover" alt="">
            </div>
            <div class="absolute -bottom-6 -left-6 bg-white rounded-2xl shadow-xl p-4 flex items-center gap-3 fade-in">
                <div class="w-12 h-12 rounded-full bg-brand/10 flex items-center justify-center">
                    <x-icon name="fire" class="w-6 h-6 text-brand"/>
                </div>
                <div>
                    <p class="text-xs text-gray-500">En tendencia</p>
                    <p class="font-bold text-sm">+1.200 ventas hoy</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CATEGORÍAS --}}
<section class="max-w-7xl mx-auto px-4 py-14">
    <div class="flex items-end justify-between mb-6">
        <h2 class="text-2xl md:text-3xl font-black">Categorías</h2>
    </div>
    <div class="grid grid-cols-3 md:grid-cols-5 gap-4">
        @foreach($categories as $cat)
            <a href="{{ route('categories.show', $cat) }}" class="group text-center">
                <div class="aspect-square rounded-full overflow-hidden mb-2 ring-1 ring-gray-200 group-hover:ring-2 group-hover:ring-brand transition">
                    <img src="{{ $cat->image }}" alt="{{ $cat->name }}"
                         class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                </div>
                <p class="text-sm font-medium group-hover:text-brand transition">{{ $cat->name }}</p>
            </a>
        @endforeach
    </div>
</section>

{{-- FLASH SALE --}}
@php $flash = \App\Models\Product::active()->whereNotNull('discount_price')->latest()->take(6)->get(); @endphp
@if($flash->isNotEmpty())
<section class="bg-gradient-to-r from-rose-500 to-brand text-white">
    <div class="max-w-7xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <x-icon name="fire" class="w-7 h-7"/>
                <h2 class="text-2xl md:text-3xl font-black">Flash Sale</h2>
                <span class="bg-white/20 backdrop-blur text-xs font-bold px-3 py-1 rounded-full">Termina pronto</span>
            </div>
            <a href="{{ route('products.index') }}" class="text-sm text-white/90 hover:text-white underline">Ver todo →</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach($flash as $product)
                <a href="{{ route('products.show', $product) }}" class="block group">
                    <div class="aspect-[3/4] rounded-xl overflow-hidden bg-white">
                        <img src="{{ $product->image }}" alt="{{ $product->name }}"
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                    </div>
                    <div class="pt-2">
                        <p class="text-sm font-bold">{{ number_format($product->current_price, 2, ',', '.') }} €</p>
                        <p class="text-xs text-white/70 line-through">{{ number_format($product->price, 2, ',', '.') }} €</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- DESTACADOS --}}
<section class="max-w-7xl mx-auto px-4 py-14">
    <div class="flex items-end justify-between mb-6">
        <h2 class="text-2xl md:text-3xl font-black">Destacados</h2>
        <a href="{{ route('products.index') }}" class="text-sm text-gray-500 hover:text-ink">Ver todo →</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
        @foreach($featured as $product)
            @include('partials.product-card', ['product' => $product])
        @endforeach
    </div>
</section>

{{-- NOVEDADES con scroll horizontal --}}
<section class="max-w-7xl mx-auto px-4 py-10">
    <div class="flex items-end justify-between mb-6">
        <h2 class="text-2xl md:text-3xl font-black">Recién llegados</h2>
        <a href="{{ route('products.index', ['sort' => 'newest']) }}" class="text-sm text-gray-500 hover:text-ink">Ver todo →</a>
    </div>
    <div class="flex gap-4 overflow-x-auto no-scrollbar snap-x snap-mandatory -mx-4 px-4 pb-2">
        @foreach($newArrivals as $product)
            <div class="w-48 md:w-56 flex-shrink-0 snap-start">
                @include('partials.product-card', ['product' => $product])
            </div>
        @endforeach
    </div>
</section>

{{-- BANNERS --}}
<section class="max-w-7xl mx-auto px-4 py-10 grid md:grid-cols-2 gap-4">
    <a href="{{ route('products.index') }}" class="relative rounded-3xl overflow-hidden group aspect-[16/10]">
        <img src="https://picsum.photos/seed/banner-1/1200/750"
             class="w-full h-full object-cover group-hover:scale-105 transition duration-700" alt="">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent flex items-end p-8">
            <div class="text-white">
                <p class="text-xs uppercase tracking-widest mb-1 opacity-80">Colección</p>
                <h3 class="text-2xl md:text-3xl font-black">Verano 2026</h3>
                <p class="text-sm opacity-90 mt-1">Hasta -50% en selección</p>
            </div>
        </div>
    </a>
    <a href="{{ route('products.index') }}" class="relative rounded-3xl overflow-hidden group aspect-[16/10]">
        <img src="https://picsum.photos/seed/banner-2/1200/750"
             class="w-full h-full object-cover group-hover:scale-105 transition duration-700" alt="">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent flex items-end p-8">
            <div class="text-white">
                <p class="text-xs uppercase tracking-widest mb-1 opacity-80">Básicos</p>
                <h3 class="text-2xl md:text-3xl font-black">Tu armario esencial</h3>
                <p class="text-sm opacity-90 mt-1">Desde 9,99 €</p>
            </div>
        </div>
    </a>
</section>

{{-- PROMESAS --}}
<section class="bg-ink text-white my-10">
    <div class="max-w-7xl mx-auto px-4 py-10 grid md:grid-cols-3 gap-6 text-center">
        <div><p class="text-2xl font-black">🚚 Envío gratis</p><p class="text-gray-400 text-sm mt-1">En pedidos +50 €</p></div>
        <div><p class="text-2xl font-black">↩️ 30 días</p><p class="text-gray-400 text-sm mt-1">Devoluciones fáciles</p></div>
        <div><p class="text-2xl font-black">🔒 Pago seguro</p><p class="text-gray-400 text-sm mt-1">SSL y 3D Secure</p></div>
    </div>
</section>

@endsection