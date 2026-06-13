<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Traits;

use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasWallet
{
    public function wallet(): MorphOne
    {
        $walletModel = config('easy-wallet.models.wallet', \YasserElgammal\LaravelEasyWallet\Models\Wallet::class);
        return $this->morphOne($walletModel, 'walletable');
    }

    public static function bootHasWallet(): void
    {
        static::created(function ($model) {
            if (property_exists($model, 'autoCreateWallet') && $model->autoCreateWallet === false) {
                return;
            }
            $model->wallet()->create();
        });
    }
}
