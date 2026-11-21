<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private CacheService $cache) {}

    public function index(Request $request)
    {
        // Hot path: catálogo con filtros + paginación. La clave cacheada
        // codifica q + categoría + orden + página, así que cada combinación
        // tiene su entrada en Redis. Invalida al mutar productos (bumpVersion
        // 'catalog'). Si Redis cae, sirve desde BD.
        $suffix = 'list:' . md5(serialize([
            $request->q, $request->category, $request->get('sort', 'newest'), $request->get('page', 1),
        ]));

        $payload = $this->cache->rememberVersioned('catalog', $suffix, function () use ($request) {
            $query = Product::active()->with('category');

            if ($request->filled('q')) {
                $query->where('name', 'like', '%'.$request->q.'%');
            }
            if ($request->filled('category')) {
                $query->whereHas('category', fn ($q) => $q
                    ->where('slug', $request->category)
                    ->where('is_active', true));
            }
            $query = match ($request->get('sort', 'newest')) {
                'price_asc'  => $query->orderBy('price'),
                'price_desc' => $query->orderBy('price', 'desc'),
                default      => $query->latest(),
            };

            return [
                'products'   => $query->paginate(12)->withQueryString(),
                'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            ];
        });

        return view('products.index', $payload);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        // Detalle de producto: clave por id (estable aunque cambie el slug).
        $data = $this->cache->rememberVersioned('catalog', "show:{$product->id}", function () use ($product) {
            $product->load(['images', 'category']);
            $related = Product::active()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->take(4)
                ->get();

            return ['product' => $product, 'related' => $related];
        });

        return view('products.show', $data);
    }

    public function byCategory(Category $category, Request $request)
    {
        abort_unless($category->is_active, 404);

        $suffix = "cat:{$category->slug}:" . md5(serialize([
            $request->get('sort', 'newest'), $request->get('page', 1),
        ]));

        $payload = $this->cache->rememberVersioned('catalog', $suffix, function () use ($category, $request) {
            $query = Product::active()
                ->where('category_id', $category->id)
                ->with('category');
            $query = match ($request->get('sort', 'newest')) {
                'price_asc'  => $query->orderBy('price'),
                'price_desc' => $query->orderBy('price', 'desc'),
                default      => $query->latest(),
            };

            return [
                'products'   => $query->paginate(12)->withQueryString(),
                'categories' => Category::where('is_active', true)->orderBy('name')->get(),
                'category'   => $category,
            ];
        });

        return view('products.index', $payload);
    }
}
