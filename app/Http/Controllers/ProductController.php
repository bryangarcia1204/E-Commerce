<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::active()->with('category');

        if ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        $query = match ($request->get('sort', 'newest')) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default      => $query->latest(),
        };

        $products   = $query->paginate(12)->withQueryString();
        $categories = Category::where('is_active', true)->get();

        return view('products.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load(['images', 'category']);

        $related = Product::active()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('products.show', compact('product', 'related'));
    }

    public function byCategory(Category $category, Request $request)
    {
        $query = Product::active()->where('category_id', $category->id)->with('category');

        $query = match ($request->get('sort', 'newest')) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default      => $query->latest(),
        };

        $products   = $query->paginate(12)->withQueryString();
        $categories = Category::where('is_active', true)->get();

        return view('products.index', compact('products', 'categories', 'category'));
    }
}