<div class="max-w-7xl mx-auto px-4 py-12">
    <div class="mb-8">
        <h1 class="text-3xl font-black mb-2">Novedades &amp; tendencias</h1>
        <p class="text-sm text-gray-500">Moda, promociones y lo que se lleva esta temporada. Actualizado desde medios de moda y ofertas de nuestras tiendas asociadas.</p>
    </div>

    @if($items->isNotEmpty())
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($items as $item)
                <a href="{{ $item->source_url ?: '#' }}"
                   target="_blank" rel="noopener noreferrer"
                   class="group block rounded-2xl overflow-hidden border border-gray-100 hover:shadow-xl transition">
                    <div class="aspect-video bg-gray-100 overflow-hidden">
                        <img src="{{ $item->cover_image ?: asset('img/placeholder.svg') }}"
                             alt="{{ $item->title }}"
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                    </div>
                    <div class="p-5">
                        <span class="text-[10px] uppercase tracking-wider text-brand">{{ \App\Models\NewsItem::TYPES[$item->type] ?? 'Noticia' }}</span>
                        <h2 class="text-lg font-bold mt-1 leading-tight group-hover:text-brand">{{ $item->title }}</h2>
                        @if($item->excerpt)
                            <p class="text-sm text-gray-600 mt-2 line-clamp-3">{{ $item->excerpt }}</p>
                        @endif
                        <div class="flex items-center justify-between mt-4 text-xs text-gray-400">
                            <span>{{ $item->source_name ?? 'MODA' }}</span>
                            <span>{{ $item->published_at?->format('d/m/Y') }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-10">
            {{ $items->links() }}
        </div>
    @else
        <div class="text-center py-20 text-gray-400">
            <p class="text-4xl mb-3">📰</p>
            <p>Aún no hay novedades. Vuelve pronto o ejecuta <code class="bg-gray-100 px-1.5 py-0.5 rounded">php artisan scrape:news</code> para importar contenido.</p>
        </div>
    @endif
</div>
