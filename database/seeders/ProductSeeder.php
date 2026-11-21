<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Vestido midi floral', 'Top cropped básico', 'Pantalón cargo',
            'Falda plisada', 'Blazer oversize', 'Camiseta estampada',
            'Jeans skinny', 'Chaqueta denim', 'Vestido de lentejuelas',
            'Sudadera con capucha', 'Zapatillas running', 'Bolso tote',
            'Sandalias de tacón', 'Cinturón de cuero', 'Gafas de sol',
            'Conjunto deportivo', 'Pijama satinado', 'Abrigo largo',
            'Camisa de lino', 'Shorts vaqueros', 'Body encaje',
            'Falda mini', 'Chaleco acolchado', 'Botines Chelsea',
            'Mochila urbana', 'Gorra bordada', 'Pulsera minimalista',
            'Bufanda de lana', 'Guantes de piel', 'Calcetines pack',
        ];

        $categories = Category::all();

        foreach ($names as $i => $name) {
            $price = rand(1500, 8999) / 100; // 15.00 - 89.99
            $hasDiscount = $i % 3 === 0;
            $discountPrice = $hasDiscount ? round($price * (rand(60, 85) / 100), 2) : null;

            Product::create([
                'category_id' => $categories->random()->id,
                'name' => $name,
                'slug' => Str::slug($name) . '-' . ($i + 1),
                'description' => "Descripción de {$name}. Material de alta calidad, cómodo y moderno. Ideal para el día a día.",
                'price' => $price,
                'discount_price' => $discountPrice,
                'stock' => rand(5, 100),
                'image' => 'https://picsum.photos/seed/prod-' . ($i + 1) . '/600/800',
                'is_active' => true,
                'is_featured' => $i < 8,
            ]);
        }
    }
}