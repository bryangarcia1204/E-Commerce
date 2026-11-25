<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    /** Posiciones disponibles en el frontend (componente <x-ad-slot>). */
    public const POSITION_HOME_MID   = 'home-mid';
    public const POSITION_HOME_FLASH = 'home-flash';
    public const POSITION_CATALOG_TOP = 'catalog-top';
    public const POSITION_PRODUCT_MID = 'product-mid';
    public const POSITION_CART_TOP   = 'cart-top';
    public const POSITION_FOOTER_BAR = 'footer-bar';

    public const POSITIONS = [
        self::POSITION_HOME_MID    => 'Home · entre categorías y ofertas',
        self::POSITION_HOME_FLASH  => 'Home · bajo ofertas flash',
        self::POSITION_CATALOG_TOP => 'Catálogo · cabecera del listado',
        self::POSITION_PRODUCT_MID => 'Detalle de producto · bajo descripción',
        self::POSITION_CART_TOP    => 'Carrito · cabecera',
        self::POSITION_FOOTER_BAR  => 'Global · barra sobre el footer',
    ];

    public const TYPE_IMAGE = 'image';
    public const TYPE_HTML  = 'html';

    public const TYPES = [
        self::TYPE_IMAGE => 'Imagen + enlace (con tracking)',
        self::TYPE_HTML  => 'Snippet HTML del proveedor',
    ];

    protected $fillable = [
        'name', 'provider', 'position', 'type', 'html', 'image_url',
        'target_url', 'is_active', 'starts_at', 'ends_at', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    /** Activo + dentro de su ventana de vigencia, ordenado por sort_order. */
    public function scopeServing(Builder $query, ?string $position = null): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>=', now()))
            ->when($position, fn ($q) => $q->where('position', $position))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** CTR en porcentaje (0 si aún no hay impresiones). */
    public function getCtrAttribute(): float
    {
        if ($this->impressions <= 0) {
            return 0.0;
        }

        return round($this->clicks * 100 / $this->impressions, 2);
    }
}
