<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use YasserElgammal\LaravelEasyWallet\Facades\EasyWallet;

class Wallet extends Model
{
    protected $fillable = [
        'walletable_id',
        'walletable_type',
        'slug',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(config('easy-wallet.models.transaction', WalletTransaction::class));
    }

    public function credit(
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
        ?string $idempotencyKey = null
    ): Model {
        return EasyWallet::credit($this->walletable, $amount, $description, $reference, $meta, $idempotencyKey, $this->slug);
    }

    public function debit(
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
        ?string $idempotencyKey = null
    ): Model {
        return EasyWallet::debit($this->walletable, $amount, $description, $reference, $meta, $idempotencyKey, $this->slug);
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return (float) $this->balance >= $amount;
    }
}
