<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'sort_order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        if (! $this->path) {
            return asset('img/placeholder.svg');
        }

        if (str_starts_with($this->path, 'http')) {
            return $this->path;
        }

        return Storage::disk('public')->url($this->path);
    }
}
