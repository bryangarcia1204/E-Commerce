<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Caché del catálogo externo (dropshipping). Snapshot por par
 * (external_store, external_product_id) que sobrevive a reinicios y caídas.
 * Lo refresca el comando `cache:external-products`.
 */
class ExternalProductCache extends Model
{
    use HasFactory;

    protected $table = 'external_product_cache';

    protected $fillable = [
        'external_store', 'external_product_id', 'payload',
        'cached_at', 'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'cached_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * ¿El cacheo sigue siendo válido?
     */
    public function isValid(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
