@php $percent = $product->discount_percent; @endphp

<div class="group relative fade-in">
    <a href="{{ route('products.show', $product) }}" class="block">
        <div class="relative overflow-hidden rounded-2xl bg-gray-100 aspect-[3/4]">
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy"
                 class="w-full h-full object-cover transition duration-700 group-hover:scale-110">

            {{-- Badges --}}
            <div class="absolute top-2 left-2 flex flex-col gap-1">
                @if($percent)
                    <span class="bg-brand text-white text-[10px] font-bold px-2 py-1 rounded-md shadow">
                        -{{ $percent }}%
                    </span>
                @endif
                @if($product->is_featured)
                    <span class="bg-ink text-white text-[10px] font-bold px-2 py-1 rounded-md shadow">
                        TOP
                    </span>
                @endif
            </div>

            {{-- Wishlist (UI) --}}
            <button type="button"
                class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 backdrop-blur flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-white hover:scale-110">
                <x-icon name="heart" class="w-4 h-4"/>
            </button>

            {{-- Quick add --}}
            <div class="absolute inset-x-2 bottom-2 opacity-0 group-hover:opacity-100 translate-y-2 group-hover:translate-y-0 transition">
                <form method="POST" action="{{ route('cart.add', $product) }}">
                    @csrf
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit"
                        class="w-full bg-ink/95 backdrop-blur text-white text-xs font-semibold py-2.5 rounded-full hover:bg-brand transition">
                        Añadir rápido
                    </button>
                </form>
            </div>
        </div>

        <div class="pt-3 space-y-1.5">
            <h3 class="text-sm text-gray-900 truncate group-hover:text-brand transition">{{ $product->name }}</h3>
            <div class="flex items-baseline gap-2">
                <span class="text-base font-bold">{{ number_format($product->current_price, 2, ',', '.') }} €</span>
                @if($percent)
                    <span class="text-xs text-gray-400 line-through">{{ number_format($product->price, 2, ',', '.') }} €</span>
                @endif
            </div>
        </div>
    </a>
</div>