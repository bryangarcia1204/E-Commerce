<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class ProductController extends Controller
{
    public function __construct(private CacheService $cache) {}

    public function index(Request $request)
    {
        // Hot path: catálogo con filtros + paginación. Cacheamos el HTML
        // renderizado del parcial (NO el Paginator/Collection Eloquent) para
        // evitar "incomplete object" al deserializar. La clave codifica
        // q + categoría + orden + página → cada combinación su entrada.
        // Invalida al mutar productos (bumpVersion 'catalog').
        $suffix = 'list:' . md5(serialize([
            $request->q, $request->category, $request->get('sort', 'newest'), $request->get('page', 1),
        ]));

        $content = $this->cache->rememberVersioned('catalog', $suffix, function () use ($request) {
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

            return View::make('partials.products.index-content', [
                'products'   => $query->paginate(12)->withQueryString(),
                'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            ])->render();
        });

        $content = $this->cache->substituteCsrf($content);

        return view('products.index', [
            'title'   => 'Productos — MODA',
            'content' => $content,
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        // Detalle de producto: clave por id (estable aunque cambie el slug).
        // HTML renderizado del parcial (no objetos).
        $content = $this->cache->rememberVersioned('catalog', "show:{$product->id}", function () use ($product) {
            $product->load(['images', 'category']);
            $related = Product::active()
                ->where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->take(4)
                ->get();

            return View::make('partials.products.show-content', [
                'product' => $product,
                'related' => $related,
            ])->render();
        });

        $content = $this->cache->substituteCsrf($content);

        return view('products.show', [
            'title'   => ($product->name ?? 'Producto') . ' — MODA',
            'content' => $content,
        ]);
    }

    public function byCategory(Category $category, Request $request)
    {
        abort_unless($category->is_active, 404);

        $suffix = "cat:{$category->slug}:" . md5(serialize([
            $request->get('sort', 'newest'), $request->get('page', 1),
        ]));

        $content = $this->cache->rememberVersioned('catalog', $suffix, function () use ($category, $request) {
            $query = Product::active()
                ->where('category_id', $category->id)
                ->with('category');
            $query = match ($request->get('sort', 'newest')) {
                'price_asc'  => $query->orderBy('price'),
                'price_desc' => $query->orderBy('price', 'desc'),
                default      => $query->latest(),
            };

            return View::make('partials.products.index-content', [
                'products'   => $query->paginate(12)->withQueryString(),
                'categories' => Category::where('is_active', true)->orderBy('name')->get(),
                'category'   => $category,
            ])->render();
        });

        $content = $this->cache->substituteCsrf($content);

        return view('products.index', [
            'title'   => $category->name . ' — MODA',
            'content' => $content,
        ]);
    }
}
