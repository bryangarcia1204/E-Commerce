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
        $categories  = Category::where('is_active', true)->take(10)->get();

        return view('home', compact('featured', 'newArrivals', 'categories'));
    }
}