<?php

return [
    // Prefix used for all wallet transaction numbers
    'transaction_prefix' => env('WALLET_TXN_PREFIX', 'TXN-'),

    // Configure the models to be used by the package
    'models' => [
        'wallet' => \YasserElgammal\LaravelEasyWallet\Models\Wallet::class,
        'transaction' => \YasserElgammal\LaravelEasyWallet\Models\WalletTransaction::class,
    ],
];
