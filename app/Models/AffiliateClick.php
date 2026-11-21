<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de un clic saliente hacia una tienda externa (dropshipping).
 * Sirve para auditoría de comisiones de afiliación y detección de fraude.
 * Se crea desde AffiliateRedirectController justo antes del redirect 302.
 */
class AffiliateClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'product_id', 'external_store', 'redirect_url',
        'sale_price', 'estimated_commission', 'ip_address', 'user_agent',
        'session_id',
    ];

    protected $casts = [
        'sale_price' => 'decimal:2',
        'estimated_commission' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
