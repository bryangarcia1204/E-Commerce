<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'name', 'slug', 'description', 'price',
        'discount_price', 'stock', 'image', 'is_active', 'is_featured',
        // Dropshipping / afiliación
        'external_store', 'external_product_id', 'external_url',
        'affiliate_tag', 'cost_price', 'commission_rate',
        'commission_type', 'is_dropshipping',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'discount_price'  => 'decimal:2',
        'cost_price'      => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'stock'           => 'integer',
        'is_active'       => 'boolean',
        'is_featured'     => 'boolean',
        'is_dropshipping' => 'boolean',
        'deleted_at'      => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Resolución de ruta por slug en lugar de por id.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * URL pública de la imagen principal.
     *  - Si la imagen es una URL absoluta (semillas del seeder, CDN externo)
     *    se devuelve tal cual.
     *  - Si es una ruta del disco "public", se construye con Storage::url()
     *    (funciona con el symlink public/storage).
     *  - Si no hay imagen, se usa un placeholder local servido desde
     *    /img/placeholder.svg para no depender de servicios externos.
     */
    public function getImageUrlAttribute(): string
    {
        if (! $this->image) {
            return asset('img/placeholder.svg');
        }

        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return Storage::disk('public')->url($this->image);
    }

    /**
     * Precio efectivo: si hay precio de descuento, prevalece.
     */
    public function getCurrentPriceAttribute(): float
    {
        return (float) ($this->discount_price ?? $this->price);
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if (! $this->discount_price || $this->price <= 0) {
            return null;
        }

        return (int) round((1 - $this->discount_price / $this->price) * 100);
    }

    /**
     * URL de afiliado: external_url con el tag de afiliado inyectado como
     * query string. Si no hay external_url ni tag, devuelve la URL interna
     * del producto. Esto es lo que se usa en el botón "Comprar en {tienda}".
     */
    public function getAffiliateUrlAttribute(): string
    {
        if (! $this->external_url) {
            return route('products.show', $this);
        }

        if (! $this->affiliate_tag) {
            return $this->external_url;
        }

        $sep = str_contains($this->external_url, '?') ? '&' : '?';

        // Cada tienda usa su propio parámetro de afiliado; mapeamos los
        // conocidos y, por defecto, usamos ?affid=. Esto evita hardcodear
        // el parámetro de una sola plataforma.
        $param = match ($this->external_store) {
            'amazon'      => 'tag',
            'aliexpress'  => 'aff_fcid',
            'shein'       => 'url_from',
            default       => 'affid',
        };

        return $this->external_url.$sep.http_build_query([$param => $this->affiliate_tag]);
    }

    /**
     * Margen unitario = precio de venta efectivo - coste. Cero si el
     * producto no es dropshipping o no tiene cost_price.
     */
    public function getUnitProfitAttribute(): float
    {
        if (! $this->is_dropshipping || $this->cost_price === null) {
            return 0.0;
        }

        return round($this->current_price - (float) $this->cost_price, 2);
    }

    /**
     * Comisión estimada por unidad (lo que la tienda paga al promotor
     * por canalizar la venta). Aplica commission_rate sobre el precio
     * de venta (percent) o como importe fijo (fixed).
     */
    public function getUnitCommissionAttribute(): float
    {
        if (! $this->is_dropshipping || (float) $this->commission_rate <= 0) {
            return 0.0;
        }

        if ($this->commission_type === 'fixed') {
            return (float) $this->commission_rate;
        }

        return round($this->current_price * (float) $this->commission_rate / 100, 2);
    }

    /**
     * ¿Quedan unidades vendibles?
     */
    public function isInStock(int $qty = 1): bool
    {
        return $this->stock >= $qty;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOnSale(Builder $query): Builder
    {
        return $query->whereNotNull('discount_price');
    }
}
