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

        $this->mergeConfigFrom(
            __DIR__ . '/../config/easy-wallet.php',
            'easy-wallet'
        );
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/easy-wallet.php' => config_path('easy-wallet.php'),
            ], 'easy-wallet-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'easy-wallet-migrations');

            $this->publishes([
                __DIR__ . '/Models/Wallet.php' => app_path('Models/Wallet.php'),
                __DIR__ . '/Models/WalletTransaction.php' => app_path('Models/WalletTransaction.php'),
            ], 'easy-wallet-models');
        }
    }
}
