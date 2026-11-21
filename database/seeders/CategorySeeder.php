<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Mujer', 'Hombre', 'Vestidos', 'Tops', 'Pantalones',
            'Zapatos', 'Bolsos', 'Accesorios', 'Belleza', 'Niños',
        ];

        foreach ($categories as $name) {
            Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'image' => 'https://picsum.photos/seed/cat-' . Str::slug($name) . '/400/400',
                'is_active' => true,
            ]);
        }
    }
}