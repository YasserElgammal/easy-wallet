<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use YasserElgammal\LaravelEasyWallet\Facades\EasyWallet;

class WalletTransaction extends Model
{
    protected $fillable = [
        'amount',
        'type',
        'description',
        'wallet_id',
        'transaction_number',
        'from_wallet_id',
        'to_wallet_id',
        'idempotency_key',
        'payload_hash',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'parent_transaction_id',
        'is_refunded',
        'refunded_amount',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'is_refunded' => 'boolean',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(config('easy-wallet.models.wallet', Wallet::class));
    }

    public function fromWallet(): BelongsTo
    {
        return $this->belongsTo(config('easy-wallet.models.wallet', Wallet::class), 'from_wallet_id');
    }

    public function toWallet(): BelongsTo
    {
        return $this->belongsTo(config('easy-wallet.models.wallet', Wallet::class), 'to_wallet_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function parentTransaction(): BelongsTo
    {
        return $this->belongsTo(config('easy-wallet.models.transaction', WalletTransaction::class), 'parent_transaction_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(config('easy-wallet.models.transaction', WalletTransaction::class), 'parent_transaction_id');
    }

    public function refund(?float $amount = null, ?string $description = null): Model
    {
        return EasyWallet::refund($this, $amount, $description);
    }
}
