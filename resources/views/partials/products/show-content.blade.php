<div class="max-w-7xl mx-auto px-4 py-6">

    <nav class="text-xs text-gray-500 mb-6 space-x-2">
        <a href="{{ route('home') }}" class="hover:text-ink">Inicio</a><span>/</span>
        <a href="{{ route('categories.show', $product->category) }}" class="hover:text-ink">{{ $product->category->name }}</a>
    </nav>

    <div class="grid md:grid-cols-2 gap-10">
        {{-- Galería --}}
        <div>
            <div class="aspect-[3/4] rounded-3xl overflow-hidden bg-gray-100 mb-3">
                <img src="{{ $product->image_url }}" class="w-full h-full object-cover" alt="{{ $product->name }}">
            </div>
            @if($product->images->isNotEmpty())
                <div class="grid grid-cols-4 gap-3">
                    @foreach($product->images as $img)
                        <div class="aspect-square rounded-xl overflow-hidden bg-gray-100 ring-1 ring-transparent hover:ring-ink transition cursor-pointer">
                            <img src="{{ $img->url }}" class="w-full h-full object-cover" alt="">
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div class="space-y-6 md:sticky md:top-24 md:self-start">
            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500">{{ $product->category->name }}</p>
                <h1 class="text-3xl md:text-4xl font-black mt-1">{{ $product->name }}</h1>
            </div>

            <div class="flex items-baseline gap-3">
                <span class="text-4xl font-black">{{ number_format($product->current_price, 2, ',', '.') }} €</span>
                @if($product->discount_percent)
                    <span class="text-xl text-gray-400 line-through">{{ number_format($product->price, 2, ',', '.') }} €</span>
                    <span class="bg-brand text-white text-xs font-bold px-2 py-1 rounded-md">-{{ $product->discount_percent }}%</span>
                @endif
            </div>

            <div x-data="{ size: '' }">
                <p class="text-sm font-semibold mb-2">Talla</p>
                <div class="flex flex-wrap gap-2">
                    @foreach(['XS','S','M','L','XL'] as $size)
                        <button type="button" @click="size = '{{ $size }}'"
                            :class="size === '{{ $size }}' ? 'border-ink bg-ink text-white' : 'border-gray-300 hover:border-ink hover:bg-ink hover:text-white'"
                            class="min-w-[52px] h-11 border rounded-xl text-sm font-medium transition">
                            {{ $size }}
                        </button>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-1">Selecciona tu talla antes de añadir al carrito.</p>

                <form method="POST" action="{{ route('cart.add', $product) }}" class="flex gap-3 mt-4">
                    {{-- Placeholder CSRF: este parcial se cachea como HTML; el
                        controlador sustituye __CSRF_TOKEN__ por el token real
                        de la sesión en cada request (evita 419 entre usuarios). --}}
                    <input type="hidden" name="_token" value="__CSRF_TOKEN__">
                    <input type="hidden" name="size" :value="size">
                    <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}"
                        class="w-20 border border-gray-300 rounded-xl text-center focus:border-ink focus:ring-0">
                    <button type="submit"
                        class="flex-1 bg-ink text-white rounded-xl py-3 font-semibold hover:bg-brand transition disabled:opacity-50"
                        {{ $product->stock < 1 ? 'disabled' : '' }}>
                        {{ $product->stock < 1 ? 'Sin stock' : 'Añadir al carrito' }}
                    </button>
                    <button type="button"
                        data-wishlist
                        data-wishlist-id="{{ $product->id }}"
                        data-wishlist-url="{{ route('wishlist.toggle', $product) }}"
                        aria-label="Añadir a favoritos"
                        class="wishlist-btn w-12 h-12 border border-gray-300 rounded-xl flex items-center justify-center hover:bg-gray-50 transition">
                        <x-icon name="heart" class="w-5 h-5 wishlist-icon"/>
                    </button>
                </form>
            </div>

            {{-- CTA de afiliación: productos dropshipping con tienda externa.
                 Pasa por /ir/{product} (AffiliateRedirectController) para
                 registrar el click con sub_id + tag de afiliado. --}}
            @if($product->is_dropshipping && $product->external_url)
                <div class="border border-amber-200 bg-amber-50 rounded-xl p-4 space-y-3">
                    <p class="text-sm font-semibold text-amber-900">
                        🛍️ Producto de tienda asociada
                    </p>
                    <p class="text-xs text-amber-800">
                        {{ ucfirst($product->external_store ?? 'la tienda') }} gestiona el pago y el envío.
                        Al pulsar te llevamos a la tienda original a través de nuestro enlace de afiliado.
                    </p>
                    <a href="{{ route('affiliate.redirect', $product) }}"
                       target="_blank" rel="noopener noreferrer nofollow sponsored"
                       class="block w-full text-center bg-black text-white rounded-xl py-3 font-semibold hover:bg-gray-800 transition">
                        Comprar en {{ ucfirst($product->external_store ?? 'tienda') }} →
                    </a>
                </div>
            @endif

            <div class="text-xs text-gray-500 space-y-1 border border-gray-100 rounded-xl p-3 bg-gray-50">
                <p>✅ Stock: {{ $product->stock }} uds.</p>
                <p>🚚 Envío en 24-48h · Gratis desde 50 €</p>
                <p>↩️ Devoluciones durante 30 días</p>
            </div>

            <div class="border-t border-gray-200 pt-4 text-sm text-gray-700 leading-relaxed">
                <h3 class="font-bold mb-2">Descripción</h3>
                <p>{{ $product->description }}</p>
            </div>

            {{-- Anuncio: bajo la descripción del producto. --}}
            <x-ad-slot position="product-mid" />
        </div>
    </div>

    @if($related->isNotEmpty())
        <section class="mt-20">
            <h2 class="text-2xl font-black mb-6">También te puede gustar</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                @foreach($related as $p)
                    @include('partials.product-card', ['product' => $p])
                @endforeach
            </div>
        </section>
    @endif
</div>
