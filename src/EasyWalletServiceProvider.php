<?php

namespace YasserElgammal\LaravelEasyWallet;

use Illuminate\Support\ServiceProvider;
use YasserElgammal\LaravelEasyWallet\Services\EasyWalletOperation;

class EasyWalletServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('easy-wallet', function () {
            return new EasyWalletOperation();
        });
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'easy-wallet-migrations');
        $this->publishes([
            __DIR__ . '/Models/Wallet.php' => app_path('Models/Wallet.php'),
            __DIR__ . '/Models/WalletTransaction.php' => app_path('Models/WalletTransaction.php'),
        ], 'easy-wallet-models');
    }
}
