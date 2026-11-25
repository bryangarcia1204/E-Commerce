@extends('admin.layouts.app')
@section('title', 'Afiliación — Admin')
@section('heading', 'Afiliación · Clics y comisiones')

@section('content')

{{-- Filtros --}}
<form method="GET" class="bg-white rounded-2xl shadow-sm p-4 mb-6 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Desde</label>
        <input type="date" name="from" value="{{ $from }}"
            class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Hasta</label>
        <input type="date" name="to" value="{{ $to }}"
            class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-600 mb-1">Red</label>
        <select name="network" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
            <option value="">Todas</option>
            @foreach($networkOptions ?? [] as $slug => $label)
                <option value="{{ $slug }}" @selected($network === $slug)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="bg-ink text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-800">
        Filtrar
    </button>
    <a href="{{ route('admin.affiliate.export', ['from' => $from, 'to' => $to]) }}"
       class="ml-auto text-sm font-semibold text-brand hover:underline">
        ⬇ Exportar CSV
    </a>
</form>

{{-- KPIs --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Clics (rango)</p>
        <p class="text-3xl font-black mt-1">{{ number_format($totalClicks) }}</p>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Clics hoy</p>
        <p class="text-3xl font-black mt-1">{{ number_format($todayClicks) }}</p>
    </div>
    <div class="bg-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs text-gray-500 uppercase tracking-wider">Productos con clic</p>
        <p class="text-3xl font-black mt-1">{{ number_format($uniqueProducts) }}</p>
    </div>
    <div class="bg-brand text-white rounded-2xl p-5 shadow-sm">
        <p class="text-xs uppercase tracking-wider opacity-80">Comisión estimada</p>
        <p class="text-3xl font-black mt-1">{{ number_format($estimatedComm, 2, ',', '.') }} €</p>
    </div>
</div>

{{-- Gráfico --}}
<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <h2 class="font-bold mb-4">Clics por día</h2>
    <canvas id="clicksChart" height="90"></canvas>
</div>

<div class="grid md:grid-cols-2 gap-6 mb-6">
    {{-- Top productos --}}
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-bold mb-4">Top productos</h2>
        @forelse($topProducts as $row)
            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                    <div class="flex items-center gap-3 min-w-0">
                    {{-- FIX: se usaba $row->product?->image (ruta cruda) y
                         rompía la miniatura; image_url resuelve CDN/storage. --}}
                    <img src="{{ $row->product?->image_url ?? asset('img/placeholder.svg') }}" alt="" class="w-8 h-10 object-cover rounded">
                    <span class="truncate text-sm">{{ $row->product?->name ?? '—' }}</span>
                </div>
                <span class="text-sm font-bold ml-2">{{ $row->total }}</span>
            </div>
        @empty
            <p class="text-sm text-gray-500">Sin datos en el rango.</p>
        @endforelse
    </div>

    {{-- Top tiendas (slug normalizado: la red del clic, con respaldo en el
         external_store del producto) --}}
    <div class="bg-white rounded-2xl shadow-sm p-5">
        <h2 class="font-bold mb-4">Top tiendas</h2>
        @forelse($topStores as $row)
            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                <span class="text-sm">{{ ucfirst($row->store ?? '—') }}</span>
                <span class="text-sm font-bold">{{ $row->total }}</span>
            </div>
        @empty
            <p class="text-sm text-gray-500">Sin datos en el rango.</p>
        @endforelse
    </div>
</div>

{{-- Tabla --}}
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-bold">Últimos clics</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Fecha</th>
                    <th class="px-5 py-3 text-left">Producto</th>
                    <th class="px-5 py-3 text-left">Usuario</th>
                    <th class="px-5 py-3 text-left">IP</th>
                    <th class="px-5 py-3 text-right">Comisión</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($clicks as $c)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-5 py-3 whitespace-nowrap text-xs text-gray-500">
                            {{ $c->created_at?->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-5 py-3">{{ $c->product?->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-xs">{{ $c->user?->email ?? 'Anónimo' }}</td>
                        <td class="px-5 py-3 text-xs font-mono">{{ $c->ip_address }}</td>
                        <td class="px-5 py-3 text-right">{{ number_format($c->commission_amount, 2, ',', '.') }} €</td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('admin.affiliate.destroy', $c->id) }}"
                                  onsubmit="return confirm('¿Eliminar este clic?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-gray-500">Sin clics.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $clicks->links() }}</div>
</div>

{{-- Chart.js vía jsdelivr --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('clicksChart');
        if (!ctx || typeof Chart === 'undefined') return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($series['labels']),
                datasets: [{
                    label: 'Clics',
                    data: @json($series['data']),
                    borderColor: '#ff2d55',
                    backgroundColor: 'rgba(255,45,85,0.1)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    });
</script>

{{-- Si no carga el gráfico por bloqueo del CDN, instálalo local: npm install chart.js y luego import Chart from 'chart.js/auto'; window.Chart = Chart; en resources/js/app.js. --}}
@endsection