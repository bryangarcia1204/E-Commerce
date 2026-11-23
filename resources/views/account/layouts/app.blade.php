<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mi cuenta — MODA')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 bg-gray-50 antialiased min-h-screen flex flex-col">

<header class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
        <a href="{{ route('home') }}" class="text-2xl font-black tracking-tighter">MODA</a>
        <nav class="flex items-center gap-4 text-sm">
            <a href="{{ route('home') }}" class="text-gray-600 hover:text-ink">Ver tienda</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-gray-500 hover:text-red-600">Salir</button>
            </form>
        </nav>
    </div>
</header>

<main class="flex-1">
    <div class="max-w-7xl mx-auto px-4 py-8 grid md:grid-cols-[220px_1fr] gap-8">

        {{-- Sidebar --}}
        <aside class="space-y-1 text-sm">
            <p class="text-xs uppercase tracking-widest text-gray-500 mb-2 px-3">Mi cuenta</p>
            <a href="{{ route('account.dashboard') }}"
               class="block px-3 py-2 rounded-lg hover:bg-white {{ request()->routeIs('account.dashboard') ? 'bg-white font-semibold shadow-sm' : '' }}">
                Resumen
            </a>
            <a href="{{ route('account.orders.index') }}"
               class="block px-3 py-2 rounded-lg hover:bg-white {{ request()->routeIs('account.orders.*') ? 'bg-white font-semibold shadow-sm' : '' }}">
                Mis pedidos
            </a>
            <a href="{{ route('account.wallet.index') }}"
                class="block px-3 py-2 rounded-lg hover:bg-white {{ request()->routeIs('account.wallet.*') ? 'bg-white font-semibold shadow-sm' : '' }}">
                Billetera MODA
            </a>
            <a href="{{ route('account.profile.edit') }}"
               class="block px-3 py-2 rounded-lg hover:bg-white {{ request()->routeIs('account.profile.*') ? 'bg-white font-semibold shadow-sm' : '' }}">
                Perfil y contraseña
            </a>
            <a href="{{ route('cart.index') }}" class="block px-3 py-2 rounded-lg hover:bg-white">
                Mi carrito
            </a>
        </aside>

        {{-- Contenido --}}
        <section>
            @if(session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </section>
    </div>
</main>
</body>
</html>