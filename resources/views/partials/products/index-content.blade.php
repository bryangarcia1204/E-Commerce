<div class="max-w-7xl mx-auto px-4 py-8">

    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-black">
            {{ $category->name ?? (request('q') ? 'Resultados para "' . request('q') . '"' : 'Todos los productos') }}
        </h1>
        <p class="text-sm text-gray-500 mt-1">{{ $products->total() }} productos</p>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
        @if(request('q'))
            <input type="hidden" name="q" value="{{ request('q') }}">
        @endif

        <select name="category" onchange="this.form.submit()"
            class="border border-gray-200 rounded-full px-4 py-2 text-sm focus:border-black focus:ring-0">
            <option value="">Todas las categorías</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->slug }}" @selected(request('category') === $cat->slug)>{{ $cat->name }}</option>
            @endforeach
        </select>

        <select name="sort" onchange="this.form.submit()"
            class="border border-gray-200 rounded-full px-4 py-2 text-sm focus:border-black focus:ring-0">
            <option value="newest" @selected(request('sort', 'newest') === 'newest')>Más recientes</option>
            <option value="price_asc" @selected(request('sort') === 'price_asc')>Precio: menor a mayor</option>
            <option value="price_desc" @selected(request('sort') === 'price_desc')>Precio: mayor a menor</option>
        </select>
    </form>

    @if($products->isEmpty())
        <div class="text-center py-20 text-gray-500">
            <p class="text-2xl mb-2">😕</p>
            <p>No encontramos productos con esos filtros.</p>
            <a href="{{ route('products.index') }}" class="inline-block mt-4 underline text-sm">Ver todo</a>
        </div>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            @foreach($products as $product)
                @include('partials.product-card', ['product' => $product])
            @endforeach
        </div>

        <div class="mt-10">{{ $products->links() }}</div>
    @endif
</div>
