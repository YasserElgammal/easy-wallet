<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'amount',
        'type',
        'description',
        'wallet_id',
        'transaction_number',
        'from_wallet_id',
        'to_wallet_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
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
}
