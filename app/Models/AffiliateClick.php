<?php

namespace App\Models;

use App\Services\Affiliate\AffiliateNetworkManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateClick extends Model
{
    protected $fillable = [
        'user_id', 'product_id', 'ip_address', 'user_agent',
        'referer', 'session_id', 'sub_id', 'network',
        'external_store', 'redirect_url', 'sale_price',
        'estimated_commission', 'commission_amount',
        'status', 'converted_at', 'paid_at',
        'external_order_id', 'webhook_payload',
    ];

    protected $casts = [
        'sale_price'           => 'decimal:2',
        'estimated_commission' => 'decimal:2',
        'commission_amount'    => 'decimal:2',
        'converted_at'         => 'datetime',
        'paid_at'              => 'datetime',
        'webhook_payload'      => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        $column = $query->getModel()->getTable() . '.created_at';

        return $query
            ->when($from, fn ($q) => $q->where($column, '>=', $from . ' 00:00:00'))
            ->when($to,   fn ($q) => $q->where($column, '<=', $to   . ' 23:59:59'));
    }

    /**
     * Filtra por red aceptando mayúsculas/minúsculas y espacios (normaliza
     * por AffiliateNetworkManager). Fix: la vista de conciliación enviaba
     * 'Amazon' pero en BD se guarda 'amazon' → 0 resultados.
     */
    public function scopeForNetwork(Builder $query, ?string $network): Builder
    {
        $slug = AffiliateNetworkManager::normalize($network);

        return $query->when($slug, fn ($q) => $q->where('network', $slug));
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeConverted(Builder $q): Builder
    {
        return $q->where('status', 'converted');
    }

    public function scopePaid(Builder $q): Builder
    {
        return $q->where('status', 'paid');
    }
}
