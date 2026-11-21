<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\NewsItem;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $featured    = Product::active()->featured()->with('category')->latest()->take(8)->get();
        $newArrivals = Product::active()->with('category')->latest()->take(8)->get();
        $categories  = Category::where('is_active', true)->orderBy('name')->take(10)->get();

        // Productos en oferta para el bloque "Flash Sale".
        $flash = Product::active()
            ->onSale()
            ->with('category')
            ->latest()
            ->take(6)
            ->get();

        // Productos dropshipping (tiendas asociadas: Shein, AliExpress,
        // Amazon...). Se muestran en una sección dedicada de la home y
        // su botón "Comprar" redirige a la tienda externa con tag de
        // afiliado.
        $dropshipping = Product::active()
            ->where('is_dropshipping', true)
            ->with('category')
            ->latest()
            ->take(8)
            ->get();

        // Novedades / promociones / tendencias. Se alimentan del seeder
        // curado y del comando scrape:news (RSS feeds). Carrusel con los
        // destacados + grid con los más recientes.
        $newsLimit    = (int) config('news.home_limit', 8);
        $newsFeatured = (int) config('news.home_featured', 4);

        $newsCarousel = NewsItem::active()->featured()->latest('published_at')->take($newsFeatured)->get();
        $newsFeed     = NewsItem::active()->latest('published_at')->take($newsLimit)->get();

        return view('home', compact(
            'featured',
            'newArrivals',
            'categories',
            'flash',
            'dropshipping',
            'newsCarousel',
            'newsFeed',
        ));
    }
}
