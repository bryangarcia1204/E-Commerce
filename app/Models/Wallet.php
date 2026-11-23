<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'currency'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }

    /** Saldo en céntimos. Se calcula, nunca se guarda. */
    public function balance(): int
    {
        return (int) $this->entries()->sum('amount');
    }

    /** Saldo formateado (ej. "12,50 €"). */
    public function balanceFormatted(): string
    {
        return number_format($this->balance() / 100, 2, ',', '.') . ' €';
    }
}