<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);

        $query = Product::with('category')->latest();

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        $products = $query->paginate(15)->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $this->authorize('create', Product::class);

        $categories = Category::orderBy('name')->get();

        return view('admin.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Product::class);

        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['image'] = $this->handleImage($request, null);

        Product::create($data);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function edit(Product $product)
    {
        $this->authorize('update', $product);

        $categories = Category::orderBy('name')->get();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $data = $this->validateData($request);

        if ($data['name'] !== $product->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $product->id);
        }

        $newImage = $this->handleImage($request, $product->image);
        if ($newImage !== null) {
            $data['image'] = $newImage;
        }

        $product->update($data);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        if ($product->image && ! str_starts_with($product->image, 'http')) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Producto eliminado.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'category_id'     => ['required', 'exists:categories,id'],
            'name'            => ['required', 'string', 'max:150'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'price'           => ['required', 'numeric', 'min:0'],
            'discount_price'  => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'stock'           => ['required', 'integer', 'min:0'],
            'image'           => ['nullable', 'image', 'max:2048'],
            'is_active'       => ['nullable', 'boolean'],
            'is_featured'     => ['nullable', 'boolean'],
        ]);

        $data['is_active']   = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');

        if (empty($data['discount_price'])) {
            $data['discount_price'] = null;
        }

        return $data;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Product::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function handleImage(Request $request, ?string $oldPath): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        if ($oldPath && ! str_starts_with($oldPath, 'http')) {
            Storage::disk('public')->delete($oldPath);
        }

        return $request->file('image')->store('products', 'public');
    }
}