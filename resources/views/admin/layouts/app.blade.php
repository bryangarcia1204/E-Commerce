<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin — MODA')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans bg-gray-50 text-gray-900 antialiased min-h-screen flex">

    {{-- Sidebar --}}
    <aside class="w-60 bg-ink text-gray-300 flex-shrink-0 min-h-screen p-5 flex flex-col">
        <a href="{{ route('home') }}" class="text-white font-black text-2xl tracking-tighter mb-8">MODA</a>

        <p class="text-[10px] uppercase tracking-widest text-gray-500 mb-2">Panel</p>
        <nav class="space-y-1 text-sm mb-6">
            <a href="{{ route('admin.dashboard') }}"
               class="block px-3 py-2 rounded-lg hover:bg-white/10 hover:text-white {{ request()->routeIs('admin.dashboard') ? 'bg-white/10 text-white' : '' }}">
                Dashboard
            </a>
            <a href="{{ route('admin.products.index') }}"
               class="block px-3 py-2 rounded-lg hover:bg-white/10 hover:text-white {{ request()->routeIs('admin.products.*') ? 'bg-white/10 text-white' : '' }}">
                Productos
            </a>
            <a href="{{ route('admin.wallets.index') }}"
                class="block px-3 py-2 rounded-lg hover:bg-white/10 hover:text-white {{ request()->routeIs('admin.wallets.*') ? 'bg-white/10 text-white' : '' }}">
                Billeteras
            </a>
            <a href="{{ route('admin.orders.index') }}"
               class="block px-3 py-2 rounded-lg hover:bg-white/10 hover:text-white {{ request()->routeIs('admin.orders.*') ? 'bg-white/10 text-white' : '' }}">
                Pedidos
            </a>
            <a href="{{ route('admin.settlements.index') }}"
               class="block px-3 py-2 rounded-lg hover:bg-white/10 hover:text-white {{ request()->routeIs('admin.settlements.*') ? 'bg-white/10 text-white' : '' }}">
                Liquidaciones
            </a>
            <a href="{{ route('admin.affiliate.index') }}"
            class="block px-3 py-2 rounded-lg hover:bg-white/10 hover:text-white {{ request()->routeIs('admin.affiliate.index') ? 'bg-white/10 text-white' : '' }}">
                Afiliación
            </a>
            <a href="{{ route('admin.affiliate.reconciliation') }}"
            class="block px-3 py-2 rounded-lg hover:bg-white/10 hover:text-white {{ request()->routeIs('admin.affiliate.reconciliation') ? 'bg-white/10 text-white' : '' }}">
                Conciliación
            </a>
        </nav>

        <p class="text-[10px] uppercase tracking-widest text-gray-500 mb-2">Cuenta</p>
        <nav class="space-y-1 text-sm">
            <a href="{{ route('home') }}" class="block px-3 py-2 rounded-lg hover:bg-white/10 hover:text-white">Ver tienda</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-3 py-2 rounded-lg hover:bg-white/10 hover:text-white text-gray-400">
                    Cerrar sesión
                </button>
            </form>
        </nav>

        <div class="mt-auto pt-6 border-t border-white/10 text-xs">
            <p class="text-white font-semibold">{{ auth()->user()->name }}</p>
            <p class="text-gray-500 truncate">{{ auth()->user()->email }}</p>
        </div>
    </aside>

    {{-- Contenido --}}
    <div class="flex-1 flex flex-col">
        <header class="bg-white border-b border-gray-200 px-8 py-4 flex items-center justify-between">
            <h1 class="text-lg font-bold">@yield('heading', 'Admin')</h1>
            <a href="{{ route('home') }}" class="text-sm text-gray-500 hover:text-ink">← Volver a la tienda</a>
        </header>

        <main class="p-8 flex-1">
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
        </main>
    </div>
</body>
</html>