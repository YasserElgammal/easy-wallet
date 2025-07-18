<?php

namespace YasserElgammal\LaravelEasyWallet\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function fromWallet()
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet()
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }
}
