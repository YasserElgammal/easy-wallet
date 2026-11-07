<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use YasserElgammal\LaravelEasyWallet\Facades\EasyWallet;

trait HasWallet
{
    /**
     * Get the model's wallet by slug (defaults to 'default').
     */
    public function wallet(?string $slug = 'default'): MorphOne
    {
        $walletModel = config('easy-wallet.models.wallet', \YasserElgammal\LaravelEasyWallet\Models\Wallet::class);
        $slug = $slug ?? 'default';

        return $this->morphOne($walletModel, 'walletable')->where('slug', $slug);
    }

    /**
     * Get all wallets associated with this model.
     */
    public function wallets(): MorphMany
    {
        $walletModel = config('easy-wallet.models.wallet', \YasserElgammal\LaravelEasyWallet\Models\Wallet::class);
        return $this->morphMany($walletModel, 'walletable');
    }

    /**
     * Get all transactions associated with this model's wallets.
     */
    public function walletTransactions(): HasManyThrough
    {
        $walletModel = config('easy-wallet.models.wallet', \YasserElgammal\LaravelEasyWallet\Models\Wallet::class);
        $transactionModel = config('easy-wallet.models.transaction', \YasserElgammal\LaravelEasyWallet\Models\WalletTransaction::class);

        return $this->hasManyThrough(
            $transactionModel,
            $walletModel,
            'walletable_id',
            'wallet_id'
        )->where((new $walletModel)->getTable() . '.walletable_type', $this->getMorphClass());
    }

    /**
     * Credit funds to the model's wallet.
     */
    public function credit(
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
        ?string $idempotencyKey = null,
        string $wallet = 'default'
    ): Model {
        return EasyWallet::credit($this, $amount, $description, $reference, $meta, $idempotencyKey, $wallet);
    }

    /**
     * Debit funds from the model's wallet.
     */
    public function debit(
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
        ?string $idempotencyKey = null,
        string $wallet = 'default'
    ): Model {
        return EasyWallet::debit($this, $amount, $description, $reference, $meta, $idempotencyKey, $wallet);
    }

    /**
     * Transfer funds from this wallet to another walletable model.
     */
    public function transferTo(
        Model $toWalletable,
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
        ?string $idempotencyKey = null,
        string $fromWalletSlug = 'default',
        string $toWalletSlug = 'default'
    ): void {
        EasyWallet::transfer($this, $toWalletable, $amount, $description, $reference, $meta, $idempotencyKey, $fromWalletSlug, $toWalletSlug);
    }

    /**
     * Refund a transaction (full or partial).
     */
    public function refund(
        Model|int $transaction,
        ?float $amount = null,
        ?string $description = null
    ): Model {
        return EasyWallet::refund($transaction, $amount, $description);
    }

    /**
     * Get the current wallet balance.
     */
    public function balance(string $wallet = 'default'): float
    {
        return EasyWallet::balance($this, $wallet);
    }

    /**
     * Wallet balance attribute accessor ($model->wallet_balance).
     */
    public function getWalletBalanceAttribute(): float
    {
        return $this->balance();
    }

    /**
     * Check if the wallet has sufficient balance.
     */
    public function hasSufficientBalance(float $amount, string $wallet = 'default'): bool
    {
        return $this->balance($wallet) >= $amount;
    }

    /**
     * Automatically create default wallet when the model is created.
     */
    public static function bootHasWallet(): void
    {
        static::created(function ($model) {
            if (property_exists($model, 'autoCreateWallet') && $model->autoCreateWallet === false) {
                return;
            }
            $model->wallets()->firstOrCreate(['slug' => 'default']);
        });
    }
}
