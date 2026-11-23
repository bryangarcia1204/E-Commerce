<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateClick extends Model
{
    protected $fillable = [
        'user_id', 'product_id', 'ip_address', 'user_agent',
        'referer', 'session_id', 'sub_id', 'network',
        'commission_amount', 'status', 'converted_at', 'paid_at',
        'external_order_id', 'webhook_payload',
    ];

    protected $casts = [
        'commission_amount' => 'decimal:2',
        'converted_at'      => 'datetime',
        'paid_at'           => 'datetime',
        'webhook_payload'   => 'array',
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