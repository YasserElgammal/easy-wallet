<?php

namespace YasserElgammal\LaravelEasyWallet\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use InvalidArgumentException;
use YasserElgammal\LaravelEasyWallet\Data\RecordTransactionData;
use YasserElgammal\LaravelEasyWallet\Models\Wallet;
use YasserElgammal\LaravelEasyWallet\Models\WalletTransaction;

class EasyWalletOperation
{
    public function credit(Model $walletable, float $amount, ?string $description = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($walletable, $amount, $description) {
            $wallet = $this->getOrCreateWallet($walletable, true);
            $transactionNumber = $this->generateRandomTransactionNumber();

            $wallet->increment('balance', $amount);

            return $this->recordTransaction(new RecordTransactionData(
                wallet: $wallet,
                amount: $amount,
                type: 'credit',
                description: $description,
                transactionNumber: $transactionNumber
            ));
        });
    }

    public function debit(Model $walletable, float $amount, ?string $description = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($walletable, $amount, $description) {
            $wallet = $walletable->wallet;

            if (!$wallet) {
                throw new RuntimeException('Wallet not found');
            }

            $this->ensureSufficientBalance($wallet, $amount);
            $transactionNumber = $this->generateRandomTransactionNumber();

            $wallet->decrement('balance', $amount);

            return $this->recordTransaction(new RecordTransactionData(
                wallet: $wallet,
                amount: -$amount,
                type: 'debit',
                description: $description,
                fromWalletId: $wallet->id,
                transactionNumber: $transactionNumber
            ));
        });
    }

    public function transfer(Model $fromWalletable, Model $toWalletable, float $amount, $description = null): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        DB::transaction(function () use ($fromWalletable, $toWalletable, $amount, $description) {
            $fromWallet = $fromWalletable->wallet()->lockForUpdate()->first();
            $toWallet = $this->getOrCreateWallet($toWalletable, true);

            if (!$fromWallet) {
                throw new RuntimeException('Sender wallet not found');
            }

            if ($fromWallet->id === $toWallet->id) {
                throw new RuntimeException('Cannot transfer to the same wallet');
            }

            $this->ensureSufficientBalance($fromWallet, $amount);
            $transactionNumber = $this->generateRandomTransactionNumber();

            // Update the balance of the sending wallet
            $fromWallet->decrement('balance', $amount);
            $this->recordTransaction(new RecordTransactionData(
                wallet: $fromWallet,
                amount: -$amount,
                type: 'debit',
                description: $description,
                toWalletId: $toWallet->id,
                fromWalletId: $fromWallet->id,
                transactionNumber: $transactionNumber
            ));

            // Update the balance of the receiving wallet
            $toWallet->increment('balance', $amount);
            $this->recordTransaction(new RecordTransactionData(
                wallet: $toWallet,
                amount: $amount,
                type: 'credit',
                description: $description,
                toWalletId: $toWallet->id,
                fromWalletId: $fromWallet->id,
                transactionNumber: $transactionNumber
            ));
        });
    }

    public function balance(Model $walletable): float
    {
        return $walletable->wallet?->balance ?? 0;
    }

    private function getOrCreateWallet(Model $walletable, $lock = false): Wallet
    {
        $query = $walletable->wallet();
        $wallet = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $wallet ?? $walletable->wallet()->create();
    }

    private function ensureSufficientBalance(Model $wallet, float $amount): void
    {
        if ($wallet->balance < $amount) {
            throw new RuntimeException('Insufficient balance');
        }
    }

    private function recordTransaction(RecordTransactionData $data): WalletTransaction
    {
        return WalletTransaction::create($data->toArray());
    }

    private function generateRandomTransactionNumber()
    {
        $number = null;

        while (!$number || WalletTransaction::where('transaction_number', $number)->exists()) {
            $number = config('laravel-easy-wallet.transaction_prefix') . strtoupper(uniqid());
        }

        return $number;
    }
}
