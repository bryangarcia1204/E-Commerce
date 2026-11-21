<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'cart';
    private const FREE_SHIPPING_THRESHOLD = 50.0;
    private const SHIPPING_COST = 4.99;

    public function content(): array
    {
        return session(self::SESSION_KEY, []);
    }

    public function add(Product $product, int $qty = 1): void
    {
        $cart = $this->content();
        $id = $product->id;

        $currentQty = isset($cart[$id]) ? (int) $cart[$id]['quantity'] : 0;
        $maxAllowed = max(0, $product->stock);
        $newQty     = min($currentQty + $qty, $maxAllowed);

        if ($newQty < 1) {
            // Sin stock real: no añadimos nada.
            return;
        }

        $cart[$id] = [
            'id'       => $product->id,
            'name'     => $product->name,
            'slug'     => $product->slug,
            'price'    => $product->current_price,
            'image'    => $product->image_url,
            'quantity' => $newQty,
        ];

        session([self::SESSION_KEY => $cart]);
    }

    public function update(int $productId, int $qty): void
    {
        $cart = $this->content();
        if (!isset($cart[$productId])) {
            return;
        }

        if ($qty <= 0) {
            unset($cart[$productId]);
        } else {
            // Re-leemos stock real para no sobrepasarlo al cambiar cantidad.
            $product = Product::select(['id', 'stock'])->find($productId);
            $cart[$productId]['quantity'] = $product
                ? min($qty, $product->stock)
                : $qty;
        }

        session([self::SESSION_KEY => $cart]);
    }

    public function remove(int $productId): void
    {
        $cart = $this->content();
        unset($cart[$productId]);
        session([self::SESSION_KEY => $cart]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        return (int) array_sum(array_column($this->content(), 'quantity'));
    }

    public function subtotal(): float
    {
        return round(array_sum(array_map(
            fn($i) => $i['price'] * $i['quantity'],
            $this->content()
        )), 2);
    }

    public function shipping(): float
    {
        $subtotal = $this->subtotal();
        if ($subtotal <= 0 || $subtotal >= self::FREE_SHIPPING_THRESHOLD) {
            return 0.0;
        }
        return self::SHIPPING_COST;
    }

    public function total(): float
    {
        return round($this->subtotal() + $this->shipping(), 2);
    }

    public function isEmpty(): bool
    {
        return empty($this->content());
    }

    /**
     * Recarga el carrito desde la base de datos: precios actuales, slugs,
     * imagen y vigencia del producto (activo y no soft-deleted).
     *
     * Descarta líneas cuyo producto ya no existe o no está activo, para
     * no facturar productos retirados del catálogo.
     */
    public function sync(): void
    {
        $cart = $this->content();
        if (empty($cart)) {
            return;
        }

        $ids = array_keys($cart);

        /** @var Collection<int,Product> $products */
        $products = Product::active()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $fresh = [];
        foreach ($cart as $id => $line) {
            $product = $products->get($id);
            if (! $product) {
                // Producto retirado: se elimina del carrito.
                continue;
            }

            $qty = max(1, min((int) $line['quantity'], $product->stock));

            $fresh[$id] = [
                'id'       => $product->id,
                'name'     => $product->name,
                'slug'     => $product->slug,
                'price'    => $product->current_price,
                'image'    => $product->image_url,
                'quantity' => $qty,
            ];
        }

        session([self::SESSION_KEY => $fresh]);
    }
}
