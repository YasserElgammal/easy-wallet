<?php

namespace YasserElgammal\LaravelEasyWallet\Traits;

use YasserElgammal\LaravelEasyWallet\Models\Wallet;


trait HasWallet
{
    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'walletable');
    }

    public static function bootHasWallet()
    {
        static::created(function ($model) {
            if (property_exists($model, 'autoCreateWallet') && $model->autoCreateWallet === false) {
                return;
            }
            $model->wallet()->create();
        });
    }
}
