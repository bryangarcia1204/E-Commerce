<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $featured    = Product::active()->featured()->with('category')->latest()->take(8)->get();
        $newArrivals = Product::active()->with('category')->latest()->take(8)->get();
        $categories  = Category::where('is_active', true)->orderBy('name')->take(10)->get();

        // Productos en oferta para el bloque "Flash Sale". Se carga aquí
        // en lugar de dentro de la vista Blade (que ejecutaba una query
        // en cada render y dificultaba la caché).
        $flash = Product::active()
            ->onSale()
            ->with('category')
            ->latest()
            ->take(6)
            ->get();

        return view('home', compact('featured', 'newArrivals', 'categories', 'flash'));
    }
}
