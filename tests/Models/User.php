<?php

namespace YasserElgammal\LaravelEasyWallet\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use YasserElgammal\LaravelEasyWallet\Models\Wallet;

class User extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function newFactory()
    {
    return \YasserElgammal\LaravelEasyWallet\Tests\database\factories\UserFactory::new();
    }

    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'walletable');
    }
}
