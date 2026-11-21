<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;

/**
 * CartService — carrito de compra.
 *
 * Dual-mode para maximizar robustez y UX:
 *   - Usuario autenticado: el carrito se persiste en BD (carts + cart_items).
 *     Sobrevive a reinicios, caídas de servidor y cierre de sesión: el
 *     usuario vuelve a entrar y su carrito sigue ahí. Es el "cache durable"
 *     que pide el modelo dropshipping.
 *   - Invitado: el carrito vive en sesión (no hay user_id que lo vincule).
 *     Al registrarse/iniciar sesión, el carrito de sesión se migra a BD
 *     (migrateSessionToDatabase()).
 *
 * La interfaz pública (content/add/update/remove/clear/count/subtotal/
 * shipping/total/isEmpty/sync) es idéntica en ambos modos, así que
 * CartController y CheckoutController no cambian.
 */
class CartService
{
    private const SESSION_KEY = 'cart';
    private const FREE_SHIPPING_THRESHOLD = 50.0;
    private const SHIPPING_COST = 4.99;

    public function __construct(private CacheService $cache) {}

    /**
     * Invalida el conteo cacheado del carrito del usuario autenticado.
     * Se llama tras cualquier mutación (add/update/remove/clear).
     */
    private function forgetCartCount(): void
    {
        if (auth()->check()) {
            $this->cache->forget("cart:count:".auth()->id());
        }
    }

    /**
     * Carrito DB del usuario autenticado (lo crea si no existe).
     */
    private function cart(): ?Cart
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    public function content(): array
    {
        if (auth()->check()) {
            $cart = $this->cart();
            if (! $cart) {
                return [];
            }
            $items = $cart->items()->with('product')->get();

            return $items->mapWithKeys(fn (CartItem $i) => [
                $i->product_id => [
                    'id'       => $i->product_id,
                    'name'     => $i->product?->name ?? '(producto eliminado)',
                    'slug'     => $i->product?->slug ?? '',
                    'price'    => (float) $i->price,
                    'image'    => $i->product?->image_url ?? asset('img/placeholder.svg'),
                    'quantity' => (int) $i->quantity,
                ],
            ])->all();
        }

        return session(self::SESSION_KEY, []);
    }

    public function add(Product $product, int $qty = 1): void
    {
        if (auth()->check()) {
            $cart = $this->cart();
            $existing = $cart->items()->where('product_id', $product->id)->first();
            $currentQty = $existing ? (int) $existing->quantity : 0;
            $maxAllowed = max(0, $product->stock);
            $newQty = min($currentQty + $qty, $maxAllowed);

            if ($newQty < 1) {
                return; // sin stock real
            }

            if ($existing) {
                $existing->update([
                    'quantity' => $newQty,
                    'price' => $product->current_price, // refresca precio
                ]);
            } else {
                $cart->items()->create([
                    'product_id' => $product->id,
                    'price' => $product->current_price,
                    'quantity' => $newQty,
                ]);
            }

            $this->forgetCartCount();
            return;
        }

        // Invitado: sesión.
        $cart = $this->content();
        $id = $product->id;
        $currentQty = isset($cart[$id]) ? (int) $cart[$id]['quantity'] : 0;
        $maxAllowed = max(0, $product->stock);
        $newQty = min($currentQty + $qty, $maxAllowed);

        if ($newQty < 1) {
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
        if (auth()->check()) {
            $cart = $this->cart();
            $item = $cart->items()->where('product_id', $productId)->first();
            if (! $item) {
                return;
            }
            if ($qty <= 0) {
                $item->delete();
            } else {
                $product = Product::select(['id', 'stock'])->find($productId);
                $item->update(['quantity' => $product ? min($qty, $product->stock) : $qty]);
            }

            $this->forgetCartCount();
            return;
        }

        $cart = $this->content();
        if (! isset($cart[$productId])) {
            return;
        }
        if ($qty <= 0) {
            unset($cart[$productId]);
        } else {
            $product = Product::select(['id', 'stock'])->find($productId);
            $cart[$productId]['quantity'] = $product ? min($qty, $product->stock) : $qty;
        }
        session([self::SESSION_KEY => $cart]);
    }

    public function remove(int $productId): void
    {
        if (auth()->check()) {
            $this->cart()?->items()->where('product_id', $productId)->delete();
            $this->forgetCartCount();

            return;
        }

        $cart = $this->content();
        unset($cart[$productId]);
        session([self::SESSION_KEY => $cart]);
    }

    public function clear(): void
    {
        if (auth()->check()) {
            $this->cart()?->items()->delete();
            $this->forgetCartCount();

            return;
        }

        session()->forget(self::SESSION_KEY);
    }

    public function count(): int
    {
        // Hot path nº1: se pinta en el badge del carrito en CADA página.
        // Para usuarios autenticados cacheamos el conteo en Redis (30s) y
        // lo invalidamos en add/update/remove/clear. Para invitados, la
        // sesión ya está en memoria (sin coste de BD).
        if (auth()->check()) {
            return (int) $this->cache->remember(
                'cart:count:'.auth()->id(),
                fn () => (int) ($this->cart()?->items()->sum('quantity') ?? 0),
                (int) (config('dropship.hot_path_ttl.cart') ?? 30),
            );
        }

        return (int) array_sum(array_column($this->content(), 'quantity'));
    }

    public function subtotal(): float
    {
        return round(array_sum(array_map(
            fn ($i) => $i['price'] * $i['quantity'],
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
     * imagen y vigencia del producto (activo y no soft-deleted). Descarta
     * líneas cuyo producto ya no existe o no está activo.
     */
    public function sync(): void
    {
        if (auth()->check()) {
            $cart = $this->cart();
            if (! $cart) {
                return;
            }
            $items = $cart->items()->with('product')->get();
            foreach ($items as $item) {
                $product = $item->product;
                if (! $product || ! $product->is_active) {
                    $item->delete();
                    continue;
                }
                $item->update([
                    'price' => $product->current_price,
                    'quantity' => max(1, min((int) $item->quantity, $product->stock)),
                ]);
            }

            return;
        }

        $cart = $this->content();
        if (empty($cart)) {
            return;
        }
        $ids = array_keys($cart);
        $products = Product::active()->whereIn('id', $ids)->get()->keyBy('id');
        $fresh = [];
        foreach ($cart as $id => $line) {
            $product = $products->get($id);
            if (! $product) {
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

    /**
     * Migra el carrito de sesión (invitado) al carrito DB del usuario que
     * acaba de iniciar sesión o registrarse. Acumula cantidades respetando
     * stock. Se llama desde el listener de Login.
     */
    public function migrateSessionToDatabase(): void
    {
        if (! auth()->check()) {
            return;
        }
        $sessionCart = session(self::SESSION_KEY, []);
        if (empty($sessionCart)) {
            return;
        }
        foreach ($sessionCart as $line) {
            $product = Product::find($line['id'] ?? null);
            if ($product) {
                $this->add($product, (int) ($line['quantity'] ?? 1));
            }
        }
        session()->forget(self::SESSION_KEY);
    }
}
