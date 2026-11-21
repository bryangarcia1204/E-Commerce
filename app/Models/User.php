<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CUSTOMER = 'customer';

    // Métodos de pago soportados (slug visible en UI/checkout).
    public const PAYMENT_CARD = 'card';
    public const PAYMENT_PAYPAL = 'paypal';
    public const PAYMENT_WALLET = 'wallet';
    public const PAYMENT_COD = 'cod'; // contra reembolso

    public const PAYMENTS = [
        self::PAYMENT_CARD => 'Tarjeta',
        self::PAYMENT_PAYPAL => 'PayPal',
        self::PAYMENT_WALLET => 'Billetera MODA',
        self::PAYMENT_COD => 'Contra reembolso',
    ];

    /**
     * IMPORTANTE: 'role' NO es mass-assignable. Evita escalado de
     * privilegios si un usuario malicioso envía role=admin en el
     * registro o en el perfil. El rol se asigna solo desde seeders
     * o desde código mediante promoteToAdmin().
     *
     * 'wallet_balance' tampoco es mass-assignable desde formularios:
     * sólo se mueve a través de WalletTransaction + DB::transaction
     * para mantener el ledger inmutable y auditable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'default_shipping_address',
        'preferred_payment_method',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wallet_balance' => 'decimal:2',
            'last_login_at' => 'datetime',
            'session_started_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function promoteToAdmin(): bool
    {
        $this->role = self::ROLE_ADMIN;

        return $this->save();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function contactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class);
    }

    public function affiliateClicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    /**
     * ¿Hay una sesión activa reciente (últimos 5 min) en la tabla sessions?
     * No revela datos de otros usuarios: consulta filtrada por user_id.
     */
    public function getIsOnlineAttribute(): bool
    {
        return DB::table('sessions')
            ->where('user_id', $this->id)
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->exists();
    }

    /**
     * Segundos transcurridos desde el inicio de la sesión actual.
     */
    public function getSessionElapsedSecondsAttribute(): int
    {
        if (! $this->session_started_at) {
            return 0;
        }

        return (int) $this->session_started_at->diffInSeconds(now());
    }

    /**
     * Tiempo transcurrido legible: "1h 23m", "12m", "45s".
     */
    public function getSessionElapsedForHumansAttribute(): string
    {
        $s = $this->session_elapsed_seconds;
        if ($s <= 0) {
            return '—';
        }
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;
        if ($h > 0) {
            return "{$h}h {$m}m";
        }
        if ($m > 0) {
            return "{$m}m {$sec}s";
        }

        return "{$sec}s";
    }

    /**
     * Método de pago marcado como default por el usuario, o NULL.
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->where('is_default', true)->first()
            ?? $this->paymentMethods()->latest()->first();
    }

    /**
     * ¿Tiene saldo suficiente en la billetera para cubrir un importe?
     */
    public function canAffordWithWallet(float $amount): bool
    {
        return (float) $this->wallet_balance >= $amount;
    }

    /**
     * Acredita un movimiento en la billetera dentro de una transacción
     * DB y registra la fila en el ledger. Devuelve el nuevo saldo.
     * Lanza excepción si el débito deja saldo negativo.
     */
    public function applyWalletMovement(
        string $direction, // credit | debit
        string $type,      // promo_credit | cashback | refund | purchase | adjustment
        float $amount,
        ?string $description = null,
        ?object $source = null,
    ): float {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El importe debe ser positivo.');
        }

        return DB::transaction(function () use ($direction, $type, $amount, $description, $source) {
            // Lock pesimista para evitar race condition en el saldo.
            $fresh = self::lockForUpdate()->find($this->id);
            $current = (float) $fresh->wallet_balance;

            if ($direction === 'debit' && $current < $amount) {
                throw new \RuntimeException('Saldo insuficiente en la billetera.');
            }

            $newBalance = $direction === 'credit'
                ? $current + $amount
                : $current - $amount;

            $fresh->wallet_balance = $newBalance;
            $fresh->save();

            $fresh->walletTransactions()->create([
                'direction' => $direction,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->id,
            ]);

            // Refrescamos la instancia en memoria para reflejar el nuevo saldo.
            $this->wallet_balance = $newBalance;

            return $newBalance;
        });
    }
}
