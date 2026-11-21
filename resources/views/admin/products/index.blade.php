@extends('admin.layouts.app')

@section('title', 'Productos — Admin')
@section('heading', 'Productos')

@section('content')
<div class="flex items-center justify-between mb-6">
    <form method="GET" class="flex gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar producto..."
            class="border border-gray-200 rounded-lg px-4 py-2 text-sm focus:border-ink focus:ring-0 w-72">
        <button type="submit" class="bg-ink text-white px-4 py-2 rounded-lg text-sm font-semibold">Buscar</button>
    </form>
    <a href="{{ route('admin.products.create') }}"
       class="bg-brand text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-brand-dark">
        + Nuevo producto
    </a>
</div>

<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    @if($products->isEmpty())
        <p class="p-8 text-sm text-gray-500 text-center">No hay productos.</p>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Producto</th>
                    <th class="px-5 py-3 text-left">Categoría</th>
                    <th class="px-5 py-3 text-right">Precio</th>
                    <th class="px-5 py-3 text-right">Stock</th>
                    <th class="px-5 py-3 text-center">Estado</th>
                    <th class="px-5 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $product->image }}" class="w-10 h-12 object-cover rounded" alt="">
                                <span class="font-medium">{{ $product->name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $product->category->name }}</td>
                        <td class="px-5 py-3 text-right font-medium">{{ number_format($product->current_price, 2, ',', '.') }} €</td>
                        <td class="px-5 py-3 text-right">{{ $product->stock }}</td>
                        <td class="px-5 py-3 text-center">
                            @if($product->is_active)
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">Activo</span>
                            @else
                                <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">Inactivo</span>
                            @endif
                            @if($product->is_featured)
                                <span class="text-xs bg-brand/10 text-brand px-2 py-1 rounded ml-1">TOP</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.products.edit', $product) }}"
                               class="text-xs text-blue-600 hover:underline mr-3">Editar</a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline"
                                  onsubmit="return confirm('¿Eliminar este producto?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-4">{{ $products->links() }}</div>
    @endif
</div>
@endsection