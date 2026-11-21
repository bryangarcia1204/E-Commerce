@extends('layouts.app')
@section('title', 'Mis favoritos — MODA')
@section('description', 'Tus productos favoritos guardados en MODA.')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-10">

    <div class="mb-8 flex items-end justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-3xl font-black mb-1">Mis favoritos</h1>
            <p class="text-sm text-gray-500">Productos que has guardado para volver a ellos cuando quieras.</p>
        </div>
        <a href="{{ route('products.index') }}" class="text-sm text-gray-500 hover:text-ink underline">Seguir comprando →</a>
    </div>

    @if($products->isNotEmpty())
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            @foreach($products as $product)
                @include('partials.product-card', ['product' => $product, 'cached' => false])
            @endforeach
        </div>

        <div class="mt-10">{{ $products->links() }}</div>
    @else
        <div class="text-center py-20 text-gray-400">
            <p class="text-4xl mb-3">♡</p>
            <p class="text-lg text-gray-500 mb-1">Aún no tienes favoritos.</p>
            <p class="text-sm">Pulsa el corazón en cualquier producto para guardarlo aquí.</p>
            <a href="{{ route('products.index') }}" class="inline-block mt-5 bg-ink text-white rounded-xl px-5 py-2.5 text-sm font-semibold hover:bg-brand transition">
                Descubrir productos
            </a>
        </div>
    @endif
</div>
@endsection
