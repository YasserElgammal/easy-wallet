<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Model credit(Model $walletable, float $amount, ?string $description = null, ?Model $reference = null, ?array $meta = null, ?string $idempotencyKey = null, string $wallet = 'default')
 * @method static Model debit(Model $walletable, float $amount, ?string $description = null, ?Model $reference = null, ?array $meta = null, ?string $idempotencyKey = null, string $wallet = 'default')
 * @method static void transfer(Model $fromWalletable, Model $toWalletable, float $amount, ?string $description = null, ?Model $reference = null, ?array $meta = null, ?string $idempotencyKey = null, string $fromWalletSlug = 'default', string $toWalletSlug = 'default')
 * @method static Model refund(Model|int $transaction, ?float $amount = null, ?string $description = null)
 * @method static float balance(Model $walletable, string $wallet = 'default')
 *
 * @see \YasserElgammal\LaravelEasyWallet\Services\EasyWalletOperation
 */
class EasyWallet extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'easy-wallet';
    }
}
