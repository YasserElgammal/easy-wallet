<?php

namespace YasserElgammal\LaravelEasyWallet\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use InvalidArgumentException;
use YasserElgammal\LaravelEasyWallet\Models\Wallet;
use YasserElgammal\LaravelEasyWallet\Models\WalletTransaction;

class EasyWalletOperation
{
    public function credit(Model $walletable, float $amount, ?string $description = null): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        DB::transaction(function () use ($walletable, $amount, $description) {
            $wallet = $this->getOrCreateWallet($walletable);
            $wallet->increment('balance', $amount);
            $this->recordTransaction($wallet, $amount, 'credit', $description);
        });
    }

    public function debit(Model $walletable, float $amount, ?string $description = null): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        DB::transaction(function () use ($walletable, $amount, $description) {
            $wallet = $walletable->wallet;

            if (!$wallet) {
                throw new RuntimeException('Wallet not found');
            }

            $this->ensureSufficientBalance($wallet, $amount);
            $wallet->decrement('balance', $amount);
            $this->recordTransaction($wallet, -$amount, 'debit', $description, null, $wallet->id);
        });
    }

    public function transfer(Model $fromWalletable, Model $toWalletable, float $amount, $description = null): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        DB::transaction(function () use ($fromWalletable, $toWalletable, $amount, $description) {
            $fromWallet = $fromWalletable->wallet;
            $toWallet = $this->getOrCreateWallet($toWalletable);

            if (!$fromWallet) {
                throw new RuntimeException('Sender wallet not found');
            }

            if ($fromWallet->id === $toWallet->id) {
                throw new RuntimeException('Cannot transfer to the same wallet');
            }

            $this->ensureSufficientBalance($fromWallet, $amount);

            // Update the balance of the sending wallet
            $fromWallet->decrement('balance', $amount);
            $this->recordTransaction($fromWallet, -$amount, 'debit', $description, $toWallet->id, $fromWallet->id);

            // Update the balance of the receiving wallet
            $toWallet->increment('balance', $amount);
            $this->recordTransaction($toWallet, $amount, 'credit', $description, $toWallet->id, $fromWallet->id);
        });
    }

    public function balance(Model $walletable): float
    {
        return $walletable->wallet?->balance ?? 0;
    }

    private function getOrCreateWallet(Model $walletable): Wallet
    {
        return $walletable->wallet ?? $walletable->wallet()->create();
    }

    private function ensureSufficientBalance(Model $wallet, float $amount): void
    {
        if ($wallet->balance < $amount) {
            throw new RuntimeException('Insufficient balance');
        }
    }

    private function recordTransaction(
        Model $wallet,
        float $amount,
        string $type,
        ?string $description = null,
        ?int $toWalletId = null,
        ?int $fromWalletId = null
    ): void {
        WalletTransaction::create([
            'wallet_id'      => $wallet->id,
            'type'           => $type,
            'amount'         => $amount,
            'description'    => $description,
            'to_wallet_id'   => $toWalletId,
            'from_wallet_id' => $fromWalletId,
        ]);
    }
}
