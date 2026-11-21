<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * WishlistController — lista de favoritos por usuario (persistente en BD).
 *
 * Arquitectura: las tarjetas de producto y el detalle se cachean como HTML
 * (CacheService), así que NO se puede hornear el estado "¿es favorito de
 * ESTE usuario?" ni el token CSRF dentro del HTML cacheado. Por eso el botón
 * corazón es neutro (data-wishlist) y el JS del layout:
 *   1) lee window.wishlistIds (inyectado por layouts.app para auth),
 *   2) rellena los corazones,
 *   3) llama a toggle() por fetch con el token CSRF del <meta>.
 *
 * Aislamiento: todas las queries filtran por auth()->id(); las rutas operan
 * por product_id (no por id de fila ajena) → sin IDOR.
 */
class WishlistController extends Controller
{
    /**
     * Página de favoritos del usuario (no cacheada: es por usuario).
     */
    public function index(Request $request): View
    {
        $products = $request->user()
            ->wishlistProducts()
            ->active()
            ->with('category')
            ->orderByPivot('created_at', 'desc')
            ->paginate(12)
            ->withQueryString();

        return view('wishlist.index', ['products' => $products]);
    }

    /**
     * Alterna (añade/quita) un producto en favoritos. Responde JSON para el
     * fetch del JS, o redirige con flash si la petición no quiere JSON
     * (fallback sin JS).
     */
    public function toggle(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        // El producto debe estar activo para poder favoritarse.
        abort_unless($product->is_active, 404);

        $row = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($row) {
            $row->delete();
            $inWishlist = false;
        } else {
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
            $inWishlist = true;
        }

        $count = $user->wishlist()->count();

        if ($request->wantsJson()) {
            return response()->json([
                'inWishlist' => $inWishlist,
                'count' => $count,
                'productId' => $product->id,
            ]);
        }

        return back()->with('success', $inWishlist
            ? 'Añadido a favoritos.'
            : 'Quitado de favoritos.');
    }

    /**
     * Quita un producto de favoritos (DELETE). Mismo esquema JSON/redirect.
     */
    public function destroy(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->delete();

        $count = $user->wishlist()->count();

        if ($request->wantsJson()) {
            return response()->json([
                'inWishlist' => false,
                'count' => $count,
                'productId' => $product->id,
            ]);
        }

        return back()->with('success', 'Quitado de favoritos.');
    }

    /**
     * IDs de productos en favoritos del usuario (JSON). Lo usa el JS del layout
     * como fallback si window.wishlistIds no estuviera inyectado, o para
     * refrescar tras login.
     */
    public function ids(Request $request): JsonResponse
    {
        return response()->json([
            'ids' => $request->user()->wishlistProductIds(),
            'count' => $request->user()->wishlist()->count(),
        ]);
    }
}
