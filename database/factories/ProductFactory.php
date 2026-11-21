<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(3, true);
        $price = fake()->randomFloat(2, 15, 90);
        $hasDiscount = fake()->boolean(30);

        return [
            'category_id'    => Category::factory(),
            'name'           => $name,
            'slug'           => Str::slug($name) . '-' . fake()->randomNumber(3),
            'description'    => fake()->paragraph(),
            'price'          => $price,
            'discount_price' => $hasDiscount ? round($price * fake()->randomFloat(2, 0.6, 0.85), 2) : null,
            'stock'          => fake()->numberBetween(0, 100),
            'image'          => null, // placeholder local
            'is_active'      => true,
            'is_featured'    => fake()->boolean(20),
        ];
    }
}
