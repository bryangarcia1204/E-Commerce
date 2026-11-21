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
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'discount_price'  => 'decimal:2',
        'stock'           => 'integer',
        'is_active'       => 'boolean',
        'is_featured'     => 'boolean',
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
