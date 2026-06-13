<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Wallet extends Model
{
    protected $fillable = ['walletable_id', 'walletable_type', 'balance'];

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
}
