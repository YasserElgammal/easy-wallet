<?php

namespace YasserElgammal\LaravelEasyWallet\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use YasserElgammal\LaravelEasyWallet\Traits\HasWallet;

class User extends Model
{
    use HasFactory, HasWallet;

    protected $guarded = [];

    public static function newFactory()
    {
        return \YasserElgammal\LaravelEasyWallet\Tests\database\factories\UserFactory::new();
    }
}
