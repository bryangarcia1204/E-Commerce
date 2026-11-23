@extends('admin.layouts.app')
@section('title', 'Billeteras — Admin')
@section('heading', 'Billeteras')

@section('content')
<form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-6 flex gap-2">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por nombre o email..."
           class="border border-gray-200 rounded-lg px-4 py-2 text-sm w-72">
    <button class="bg-ink text-white px-4 py-2 rounded-lg text-sm font-semibold">Buscar</button>
</form>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
                <th class="px-5 py-3 text-left">Usuario</th>
                <th class="px-5 py-3 text-left">Email</th>
                <th class="px-5 py-3 text-right">Saldo</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($wallets as $w)
                <tr class="border-t border-gray-100 hover:bg-gray-50">
                    <td class="px-5 py-3 font-medium">{{ $w->user?->name }}</td>
                    <td class="px-5 py-3 text-xs text-gray-500">{{ $w->user?->email }}</td>
                    <td class="px-5 py-3 text-right font-bold">
                        {{ number_format($w->balance_cents / 100, 2, ',', '.') }} €
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.wallets.show', $w) }}"
                           class="text-xs text-brand hover:underline">Ver detalle</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">Sin billeteras.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-4">{{ $wallets->links() }}</div>
</div>
@endsection