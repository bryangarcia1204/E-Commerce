<?php

namespace Database\Seeders;

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

        $categories = \App\Models\Category::all();

        foreach ($names as $i => $name) {
            $price = rand(1500, 8999) / 100; // 15.00 - 89.99
            $hasDiscount = $i % 3 === 0;
            $discountPrice = $hasDiscount ? round($price * (rand(60, 85) / 100), 2) : null;

            Product::create([
                'category_id'    => $categories->random()->id,
                'name'           => $name,
                'slug'           => Str::slug($name) . '-' . ($i + 1),
                'description'    => "Descripción de {$name}. Material de alta calidad, cómodo y moderno. Ideal para el día a día.",
                'price'          => $price,
                'discount_price' => $discountPrice,
                'stock'          => rand(5, 100),
                // NULL = sin imagen; la vista usará el placeholder local
                // (public/img/placeholder.svg) en lugar de depender de picsum.
                'image'          => null,
                'is_active'      => true,
                'is_featured'    => $i < 8,
            ]);
        }

        // Productos dropshipping (tiendas asociadas). El precio incluye
        // margen sobre el coste; al comprar, el usuario es redirigido a la
        // tienda original con nuestro tag de afiliado.
        $dropshipping = [
            ['name' => 'Vestido midi floral Shein', 'store' => 'shein', 'cost' => 2.30, 'sale' => 3.20, 'commission' => 5, 'cat' => 'Vestidos'],
            ['name' => 'Top cropped básico Shein', 'store' => 'shein', 'cost' => 1.80, 'sale' => 2.95, 'commission' => 5, 'cat' => 'Tops'],
            ['name' => 'Zapatillas chunky AliExpress', 'store' => 'aliexpress', 'cost' => 8.50, 'sale' => 14.90, 'commission' => 8, 'cat' => 'Calzado'],
            ['name' => 'Bolso tote AliExpress', 'store' => 'aliexpress', 'cost' => 6.20, 'sale' => 12.50, 'commission' => 8, 'cat' => 'Bolsos'],
            ['name' => 'Gafas de sol Amazon', 'store' => 'amazon', 'cost' => 4.00, 'sale' => 9.99, 'commission' => 4, 'cat' => 'Accesorios'],
            ['name' => 'Reloj minimalista Amazon', 'store' => 'amazon', 'cost' => 7.50, 'sale' => 19.90, 'commission' => 4, 'cat' => 'Accesorios'],
            ['name' => 'Camiseta estampada Zara', 'store' => 'zara', 'cost' => 5.90, 'sale' => 9.95, 'commission' => 3, 'cat' => 'Camisetas'],
            ['name' => 'Falda plisada Zara', 'store' => 'zara', 'cost' => 12.90, 'sale' => 19.95, 'commission' => 3, 'cat' => 'Faldas'],
        ];

        $storeUrls = [
            'shein'      => 'https://www.shein.com/es/product-p-{{id}}.html',
            'aliexpress' => 'https://www.aliexpress.com/item/{{id}}.html',
            'amazon'     => 'https://www.amazon.com/dp/{{id}}',
            'zara'       => 'https://www.zara.com/es/es/product.{{id}}.html',
        ];

        foreach ($dropshipping as $k => $dp) {
            $cat = $categories->firstWhere('name', $dp['cat']) ?? $categories->random();
            $extId = 'MODA-'.strtoupper($dp['store']).'-'.(1000 + $k);
            $urlTemplate = $storeUrls[$dp['store']] ?? 'https://example.com/p/{{id}}';

            Product::create([
                'category_id'      => $cat->id,
                'name'             => $dp['name'],
                'slug'             => Str::slug($dp['name']).'-'.($k + 1),
                'description'      => "Producto de tienda asociada ({$dp['store']}). Se compra directamente en la tienda original a través de nuestro enlace de afiliado. Envío y devoluciones gestionados por la tienda.",
                'price'            => $dp['sale'],
                'discount_price'   => null,
                // Stock alto: lo gestiona la tienda externa, no limita la compra.
                'stock'            => 999,
                'image'            => null,
                'is_active'        => true,
                'is_featured'      => $k < 4,
                // Dropshipping / afiliación
                'is_dropshipping'  => true,
                'external_store'   => $dp['store'],
                'external_product_id' => $extId,
                'external_url'     => str_replace('{{id}}', $extId, $urlTemplate),
                'affiliate_tag'    => 'modaaffil-21',
                'cost_price'       => $dp['cost'],
                'commission_rate'  => $dp['commission'],
                'commission_type'  => 'percent',
            ]);
        }
    }
}
