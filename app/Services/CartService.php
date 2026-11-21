<?php

namespace App\Services;

use App\Models\Product;

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

        if (isset($cart[$id])) {
            $cart[$id]['quantity'] = min($cart[$id]['quantity'] + $qty, $product->stock);
        } else {
            $cart[$id] = [
                'id'       => $product->id,
                'name'     => $product->name,
                'slug'     => $product->slug,
                'price'    => $product->current_price,
                'image'    => $product->image,
                'quantity' => max(1, min($qty, $product->stock)),
            ];
        }

        session([self::SESSION_KEY => $cart]);
    }

    public function update(int $productId, int $qty): void
    {
        $cart = $this->content();
        if (!isset($cart[$productId])) return;

        if ($qty <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId]['quantity'] = $qty;
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
}