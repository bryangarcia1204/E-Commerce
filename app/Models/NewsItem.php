<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Item de "novedades / promociones / tendencias" mostrado en la home.
 * Se alimenta de un seeder curado y del comando scrape:news (RSS feeds).
 * Cada item enlaza opcionalmente a un producto interno o a una URL externa.
 */
class NewsItem extends Model
{
    use HasFactory;

    public const TYPE_TREND = 'trend';
    public const TYPE_PROMO = 'promo';
    public const TYPE_NEWS = 'news';
    public const TYPE_LOOKBOOK = 'lookbook';

    public const TYPES = [
        self::TYPE_TREND => 'Tendencia',
        self::TYPE_PROMO => 'Promoción',
        self::TYPE_NEWS => 'Noticia',
        self::TYPE_LOOKBOOK => 'Lookbook',
    ];

    protected $fillable = [
        'type', 'title', 'slug', 'excerpt', 'body', 'cover_image',
        'tags', 'product_id', 'source_url', 'source_name',
        'is_featured', 'is_active', 'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Auto-slug al crear si no viene dado.
        static::creating(function (self $item) {
            $item->slug ??= Str::slug($item->title).'-'.Str::random(6);
            $item->published_at ??= now();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured', true);
    }
}
