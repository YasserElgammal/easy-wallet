<?php

namespace YasserElgammal\LaravelEasyWallet\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['walletable_id', 'walletable_type', 'balance'];

    public function walletable()
    {
        return $this->morphTo();
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

}
