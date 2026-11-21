@extends('admin.layouts.app')

@section('title', 'Editar producto — Admin')
@section('heading', 'Editar producto')

@section('content')
<form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data"
      class="bg-white rounded-2xl shadow-sm p-6 max-w-3xl space-y-5">
    @csrf
    @method('PUT')

    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold mb-1">Nombre *</label>
            <input type="text" name="name" value="{{ old('name', $product->name) }}" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1">Categoría *</label>
            <select name="category_id" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-semibold mb-1">Descripción</label>
        <textarea name="description" rows="4"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">{{ old('description', $product->description) }}</textarea>
        @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-semibold mb-1">Precio *</label>
            <input type="number" step="0.01" name="price" value="{{ old('price', $product->price) }}" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
            @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1">Precio descuento</label>
            <input type="number" step="0.01" name="discount_price" value="{{ old('discount_price', $product->discount_price) }}"
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
            @error('discount_price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-semibold mb-1">Stock *</label>
            <input type="number" name="stock" value="{{ old('stock', $product->stock) }}" required
                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:border-ink focus:ring-0">
            @error('stock') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-semibold mb-1">Imagen</label>
        @if($product->image)
            <img src="{{ $product->image_url }}" class="w-24 h-32 object-cover rounded-lg mb-2 bg-gray-100" alt="">
        @endif
        <input type="file" name="image" accept="image/*"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:border-ink focus:ring-0">
        @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex gap-6 text-sm">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))> Activo
        </label>
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))> Destacado
        </label>
    </div>

    <div class="flex justify-end gap-3 pt-2">
        <a href="{{ route('admin.products.index') }}" class="px-6 py-2 rounded-full border border-gray-300 text-sm font-semibold">
            Cancelar
        </a>
        <button type="submit" class="bg-ink text-white px-6 py-2 rounded-full text-sm font-semibold hover:bg-gray-800">
            Actualizar producto
        </button>
    </div>
</form>
@endsection