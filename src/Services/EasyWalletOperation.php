<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use YasserElgammal\LaravelEasyWallet\Data\RecordTransactionData;
use YasserElgammal\LaravelEasyWallet\Models\Wallet;
use YasserElgammal\LaravelEasyWallet\Models\WalletTransaction;
use YasserElgammal\LaravelEasyWallet\Exceptions\InsufficientBalanceException;
use YasserElgammal\LaravelEasyWallet\Exceptions\WalletNotFoundException;
use YasserElgammal\LaravelEasyWallet\Exceptions\InvalidAmountException;
use YasserElgammal\LaravelEasyWallet\Events\WalletCredited;
use YasserElgammal\LaravelEasyWallet\Events\WalletDebited;
use YasserElgammal\LaravelEasyWallet\Events\WalletTransferCompleted;

class EasyWalletOperation
{
    public function credit(Model $walletable, float $amount, ?string $description = null): Model
    {
        if ($amount <= 0) {
            throw new InvalidAmountException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($walletable, $amount, $description) {
            $wallet = $this->getOrCreateWallet($walletable, true);
            $transactionNumber = $this->generateRandomTransactionNumber();

            $wallet->increment('balance', $amount);

            $transaction = $this->recordTransaction(new RecordTransactionData(
                wallet: $wallet,
                amount: $amount,
                type: 'credit',
                description: $description,
                transactionNumber: $transactionNumber
            ));

            event(new WalletCredited($transaction));

            return $transaction;
        });
    }

    public function debit(Model $walletable, float $amount, ?string $description = null): Model
    {
        if ($amount <= 0) {
            throw new InvalidAmountException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($walletable, $amount, $description) {
            // Fix race condition by locking the wallet
            $wallet = $walletable->wallet()->lockForUpdate()->first();

            if (!$wallet) {
                throw new WalletNotFoundException('Wallet not found');
            }

            $this->ensureSufficientBalance($wallet, $amount);
            $transactionNumber = $this->generateRandomTransactionNumber();

            $wallet->decrement('balance', $amount);

            $transaction = $this->recordTransaction(new RecordTransactionData(
                wallet: $wallet,
                amount: -$amount,
                type: 'debit',
                description: $description,
                fromWalletId: (int) $wallet->id,
                transactionNumber: $transactionNumber
            ));

            event(new WalletDebited($transaction));

            return $transaction;
        });
    }

    public function transfer(Model $fromWalletable, Model $toWalletable, float $amount, ?string $description = null): void
    {
        if ($amount <= 0) {
            throw new InvalidAmountException('Amount must be greater than zero.');
        }

        DB::transaction(function () use ($fromWalletable, $toWalletable, $amount, $description) {
            $fromWallet = $fromWalletable->wallet()->lockForUpdate()->first();
            $toWallet = $this->getOrCreateWallet($toWalletable, true);

            if (!$fromWallet) {
                throw new WalletNotFoundException('Sender wallet not found');
            }

            if ($fromWallet->id === $toWallet->id) {
                throw new InvalidAmountException('Cannot transfer to the same wallet');
            }

            $this->ensureSufficientBalance($fromWallet, $amount);
            $transactionNumber = $this->generateRandomTransactionNumber();

            // Update the balance of the sending wallet
            $fromWallet->decrement('balance', $amount);
            $debitTransaction = $this->recordTransaction(new RecordTransactionData(
                wallet: $fromWallet,
                amount: -$amount,
                type: 'debit',
                description: $description,
                toWalletId: (int) $toWallet->id,
                fromWalletId: (int) $fromWallet->id,
                transactionNumber: $transactionNumber
            ));

            // Update the balance of the receiving wallet
            $toWallet->increment('balance', $amount);
            $creditTransaction = $this->recordTransaction(new RecordTransactionData(
                wallet: $toWallet,
                amount: $amount,
                type: 'credit',
                description: $description,
                toWalletId: (int) $toWallet->id,
                fromWalletId: (int) $fromWallet->id,
                transactionNumber: $transactionNumber
            ));
            
            event(new WalletTransferCompleted($debitTransaction, $creditTransaction));
        });
    }

    public function balance(Model $walletable): float
    {
        return (float) ($walletable->wallet?->balance ?? 0);
    }

    private function getOrCreateWallet(Model $walletable, bool $lock = false): Model
    {
        $query = $walletable->wallet();
        $wallet = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $wallet ?? $walletable->wallet()->create();
    }

    private function ensureSufficientBalance(Model $wallet, float $amount): void
    {
        if ((float) $wallet->balance < $amount) {
            throw new InsufficientBalanceException('Insufficient balance');
        }
    }

    private function recordTransaction(RecordTransactionData $data): Model
    {
        $transactionModelClass = config('easy-wallet.models.transaction', WalletTransaction::class);
        return $transactionModelClass::create($data->toArray());
    }

    private function generateRandomTransactionNumber(): string
    {
        $transactionModelClass = config('easy-wallet.models.transaction', WalletTransaction::class);
        $number = null;

        while (!$number || $transactionModelClass::where('transaction_number', $number)->exists()) {
            $number = config('easy-wallet.transaction_prefix') . Str::uuid()->toString();
        }

        return $number;
    }
}
