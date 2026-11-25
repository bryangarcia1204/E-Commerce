<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        // IDs de favoritos del usuario (1 query pluck; reusado para el script y
        // el badge del header). [] para invitados. Lo lee resources/js/wishlist.js.
        $wishlistIds = auth()->check() ? auth()->user()->wishlistProductIds() : [];
    @endphp
    @auth
        <script>window.wishlistIds = @json($wishlistIds); window.isUserAuth = true;</script>
    @else
        <script>window.wishlistIds = []; window.isUserAuth = false; window.loginUrl = @json(route('login'));</script>
    @endauth
    <meta name="description" content="@yield('description', 'MODA: tienda online de moda. Envío gratis desde 50 € y devoluciones en 30 días.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <title>@yield('title', 'MODA — Tienda online')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 bg-white antialiased">

@inject('cart', 'App\Services\CartService')

{{-- Barra promo --}}
<div class="bg-ink text-white text-xs tracking-wide">
    <div class="max-w-7xl mx-auto px-4 py-2 text-center">
        Envío gratis en compras superiores a 50 € · Devoluciones en 30 días
    </div>
</div>

{{-- Header --}}
<header class="bg-white/90 backdrop-blur-md border-b border-gray-100 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center gap-4 h-16">

            <a href="{{ route('home') }}" class="text-2xl font-black tracking-tighter">MODA</a>

            <form action="{{ route('products.index') }}" method="GET" class="flex-1 hidden md:flex justify-center">
                <div class="relative w-full max-w-md">
                    <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500"/>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar productos..."
                        class="w-full pl-10 pr-4 py-2.5 rounded-full bg-gray-100 border-0 focus:bg-white focus:ring-2 focus:ring-ink text-sm transition">
                </div>
            </form>

            <nav class="flex items-center gap-1 md:gap-2">
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}"
                           class="hidden md:flex items-center gap-1.5 px-3 py-2 rounded-full bg-ink text-white hover:bg-gray-800 text-xs font-semibold">
                            Admin
                        </a>
                    @endif

                    <a href="{{ route('account.dashboard') }}"
                        class="hidden md:flex items-center gap-1.5 px-3 py-2 rounded-full hover:bg-gray-100 text-sm font-medium">
                        <x-icon name="user" class="w-4 h-4"/> {{ auth()->user()->name }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="hidden md:block">
                        @csrf
                        <button type="submit" class="px-3 py-2 rounded-full hover:bg-gray-100 text-sm font-medium text-gray-500">
                            Salir
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       class="hidden md:flex items-center gap-1.5 px-3 py-2 rounded-full hover:bg-gray-100 text-sm font-medium">
                        <x-icon name="user" class="w-4 h-4"/> Entrar
                    </a>
                    <a href="{{ route('register') }}"
                       class="hidden md:flex px-3 py-2 rounded-full bg-ink text-white hover:bg-gray-800 text-sm font-medium">
                        Registro
                    </a>
                @endauth

                @auth
                    <a href="{{ route('wishlist.index') }}"
                       class="relative flex items-center gap-1.5 px-3 py-2 rounded-full hover:bg-gray-100 text-sm font-medium"
                       data-wishlist-badge>
                        <x-icon name="heart" class="w-5 h-5"/>
                        <span class="hidden md:inline">Favoritos</span>
                        <span class="absolute md:static md:ml-1 -top-1 -right-1 bg-brand text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center"
                              data-wishlist-count>{{ count($wishlistIds) }}</span>
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="hidden md:flex items-center gap-1.5 px-3 py-2 rounded-full hover:bg-gray-100 text-sm font-medium">
                        <x-icon name="heart" class="w-5 h-5"/>
                        <span>Favoritos</span>
                    </a>
                @endauth

                <a href="{{ route('cart.index') }}" class="relative flex items-center gap-1.5 px-3 py-2 rounded-full hover:bg-gray-100 text-sm font-medium">
                    <x-icon name="bag" class="w-5 h-5"/>
                    <span class="hidden md:inline">Carrito</span>
                    <span class="absolute md:static md:ml-1 -top-1 -right-1 bg-brand text-white text-[10px] font-bold rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center">
                        {{ $cart->count() }}
                    </span>
                </a>
            </nav>
        </div>

        <div class="flex items-center gap-5 pb-3 overflow-x-auto no-scrollbar text-sm text-gray-700">
            <a href="{{ route('products.index') }}"
               class="whitespace-nowrap hover:text-ink transition {{ request()->routeIs('products.index') && !request('category') ? 'text-ink font-bold' : 'font-medium' }}">
                Todo
            </a>
            {{-- Categorías del menú.
                Consulta directa (10 filas, índice en is_active): barata y
                predecible. Se quitó Cache::remember porque el driver
                'database' no siempre reconstruye bien Collections de modelos
                Eloquent al deserializar y devolvía un string, rompiendo el
                foreach con "Attempt to read property 'name' on string". --}}
            @php
                $navCategories = \App\Models\Category::where('is_active', true)
                    ->orderBy('name')
                    ->take(10)
                    ->get();
            @endphp
            @foreach($navCategories as $navCat)
                <a href="{{ route('categories.show', $navCat) }}"
                   class="whitespace-nowrap hover:text-ink transition {{ request()->routeIs('categories.show') && request()->route('category')?->id === $navCat->id ? 'text-ink font-bold' : 'font-medium' }}">
                    {{ $navCat->name }}
                </a>
            @endforeach
        </div>
    </div>
</header>

{{-- Flash messages --}}
@if(session('success') || session('error') || session('status'))
    <div class="max-w-7xl mx-auto px-4 pt-4">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif
        @if(session('status'))
            <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg text-sm">
                {{ session('status') }}
            </div>
        @endif
    </div>
@endif

<main>@yield('content')</main>
<x-ad-slot position="footer-bar" />
{{-- Footer --}}
<footer class="bg-ink text-gray-400 mt-20">
    <div class="max-w-7xl mx-auto px-4 py-14 grid grid-cols-2 md:grid-cols-4 gap-8 text-sm">
        <div>
            <p class="text-white font-black text-2xl tracking-tighter mb-3">MODA</p>
            <p class="text-xs leading-relaxed max-w-xs">Moda moderna, calidad y precio justo.</p>
        </div>
        <div>
            <h4 class="text-white font-bold mb-3">Comprar</h4>
            <ul class="space-y-2 text-xs">
                <li><a href="{{ route('products.index') }}" class="hover:text-white">Todo</a></li>
                <li><a href="{{ route('products.index', ['sort' => 'newest']) }}" class="hover:text-white">Novedades</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold mb-3">Ayuda</h4>
            <ul class="space-y-2 text-xs">
                <li><a href="{{ route('pages.envios') }}" class="hover:text-white">Envíos</a></li>
                <li><a href="{{ route('pages.devoluciones') }}" class="hover:text-white">Devoluciones</a></li>
                <li><a href="{{ route('pages.contacto') }}" class="hover:text-white">Contacto</a></li>
                <li><a href="{{ route('pages.novedades') }}" class="hover:text-white">Novedades</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-white font-bold mb-3">Legal</h4>
            <ul class="space-y-2 text-xs">
                <li><a href="{{ route('pages.privacidad') }}" class="hover:text-white">Privacidad (RGPD)</a></li>
                <li><a href="{{ route('pages.terminos') }}" class="hover:text-white">Términos</a></li>
                <li><a href="{{ route('pages.nosotros') }}" class="hover:text-white">Sobre nosotros</a></li>
                <li><a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="hover:text-white">Instagram</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="max-w-7xl mx-auto px-4 py-4 text-xs flex flex-col md:flex-row justify-between gap-2">
            <p>© {{ date('Y') }} MODA — Todos los derechos reservados</p>
            <p class="text-gray-500">Hecho con Laravel ❤️</p>
        </div>
    </div>
</footer>
</body>
</html>