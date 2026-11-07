<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use YasserElgammal\LaravelEasyWallet\Data\RecordTransactionData;
use YasserElgammal\LaravelEasyWallet\Models\Wallet;
use YasserElgammal\LaravelEasyWallet\Models\WalletTransaction;
use YasserElgammal\LaravelEasyWallet\Exceptions\InsufficientBalanceException;
use YasserElgammal\LaravelEasyWallet\Exceptions\WalletNotFoundException;
use YasserElgammal\LaravelEasyWallet\Exceptions\InvalidAmountException;
use YasserElgammal\LaravelEasyWallet\Exceptions\IdempotencyConflictException;
use YasserElgammal\LaravelEasyWallet\Exceptions\TransactionAlreadyRefundedException;
use YasserElgammal\LaravelEasyWallet\Exceptions\InvalidRefundAmountException;
use YasserElgammal\LaravelEasyWallet\Events\WalletCredited;
use YasserElgammal\LaravelEasyWallet\Events\WalletDebited;
use YasserElgammal\LaravelEasyWallet\Events\WalletTransferCompleted;
use YasserElgammal\LaravelEasyWallet\Events\WalletRefunded;

class EasyWalletOperation
{
    public function credit(
        Model $walletable,
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
        ?string $idempotencyKey = null,
        string $wallet = 'default'
    ): Model {
        if ($amount <= 0) {
            throw new InvalidAmountException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($walletable, $amount, $description, $reference, $meta, $idempotencyKey, $wallet) {
            $transactionModelClass = config('easy-wallet.models.transaction', WalletTransaction::class);
            $walletModel = $this->getOrCreateWallet($walletable, true, $wallet);

            $payloadHash = null;
            if ($idempotencyKey !== null) {
                $payload = [
                    'action'         => 'credit',
                    'wallet_id'      => (int) $walletModel->id,
                    'amount'         => (string) number_format($amount, 4, '.', ''),
                    'description'    => $description,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id'   => $reference ? (string) $reference->getKey() : null,
                ];
                $payloadHash = $this->calculatePayloadHash($payload);

                $existing = $transactionModelClass::where('wallet_id', $walletModel->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    if ($existing->payload_hash && $existing->payload_hash !== $payloadHash) {
                        throw new IdempotencyConflictException(
                            "Idempotency key '{$idempotencyKey}' was already used with a different payload."
                        );
                    }
                    return $existing;
                }
            }

            $transactionNumber = $this->generateRandomTransactionNumber();
            $balanceBefore = (float) $walletModel->balance;
            $walletModel->increment('balance', $amount);
            $balanceAfter = $balanceBefore + $amount;

            $recordData = new RecordTransactionData(
                wallet: $walletModel,
                amount: $amount,
                type: 'credit',
                description: $description,
                transactionNumber: $transactionNumber,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                reference: $reference,
                meta: $meta,
                idempotencyKey: $idempotencyKey,
                payloadHash: $payloadHash
            );

            try {
                $transaction = $this->recordTransaction($recordData);
            } catch (QueryException $e) {
                if ($idempotencyKey !== null) {
                    $existing = $transactionModelClass::where('wallet_id', $walletModel->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();
                    if ($existing) {
                        if ($existing->payload_hash && $existing->payload_hash !== $payloadHash) {
                            throw new IdempotencyConflictException(
                                "Idempotency key '{$idempotencyKey}' was already used with a different payload."
                            );
                        }
                        return $existing;
                    }
                }
                throw $e;
            }

            $walletable->unsetRelation('wallet');
            $walletable->unsetRelation('wallets');

            event(new WalletCredited($transaction));

            return $transaction;
        });
    }

    public function debit(
        Model $walletable,
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
        ?string $idempotencyKey = null,
        string $wallet = 'default'
    ): Model {
        if ($amount <= 0) {
            throw new InvalidAmountException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use ($walletable, $amount, $description, $reference, $meta, $idempotencyKey, $wallet) {
            $transactionModelClass = config('easy-wallet.models.transaction', WalletTransaction::class);

            $walletModel = $walletable->wallet($wallet)->lockForUpdate()->first();

            if (!$walletModel) {
                throw new WalletNotFoundException("Wallet '{$wallet}' not found");
            }

            $payloadHash = null;
            if ($idempotencyKey !== null) {
                $payload = [
                    'action'         => 'debit',
                    'wallet_id'      => (int) $walletModel->id,
                    'amount'         => (string) number_format($amount, 4, '.', ''),
                    'description'    => $description,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id'   => $reference ? (string) $reference->getKey() : null,
                ];
                $payloadHash = $this->calculatePayloadHash($payload);

                $existing = $transactionModelClass::where('wallet_id', $walletModel->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    if ($existing->payload_hash && $existing->payload_hash !== $payloadHash) {
                        throw new IdempotencyConflictException(
                            "Idempotency key '{$idempotencyKey}' was already used with a different payload."
                        );
                    }
                    return $existing;
                }
            }

            $this->ensureSufficientBalance($walletModel, $amount);
            $transactionNumber = $this->generateRandomTransactionNumber();

            $balanceBefore = (float) $walletModel->balance;
            $walletModel->decrement('balance', $amount);
            $balanceAfter = $balanceBefore - $amount;

            $recordData = new RecordTransactionData(
                wallet: $walletModel,
                amount: -$amount,
                type: 'debit',
                description: $description,
                fromWalletId: (int) $walletModel->id,
                transactionNumber: $transactionNumber,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                reference: $reference,
                meta: $meta,
                idempotencyKey: $idempotencyKey,
                payloadHash: $payloadHash
            );

            try {
                $transaction = $this->recordTransaction($recordData);
            } catch (QueryException $e) {
                if ($idempotencyKey !== null) {
                    $existing = $transactionModelClass::where('wallet_id', $walletModel->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->first();
                    if ($existing) {
                        if ($existing->payload_hash && $existing->payload_hash !== $payloadHash) {
                            throw new IdempotencyConflictException(
                                "Idempotency key '{$idempotencyKey}' was already used with a different payload."
                            );
                        }
                        return $existing;
                    }
                }
                throw $e;
            }

            $walletable->unsetRelation('wallet');
            $walletable->unsetRelation('wallets');

            event(new WalletDebited($transaction));

            return $transaction;
        });
    }

    public function transfer(
        Model $fromWalletable,
        Model $toWalletable,
        float $amount,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
        ?string $idempotencyKey = null,
        string $fromWalletSlug = 'default',
        string $toWalletSlug = 'default'
    ): void {
        if ($amount <= 0) {
            throw new InvalidAmountException('Amount must be greater than zero.');
        }

        DB::transaction(function () use ($fromWalletable, $toWalletable, $amount, $description, $reference, $meta, $idempotencyKey, $fromWalletSlug, $toWalletSlug) {
            $transactionModelClass = config('easy-wallet.models.transaction', WalletTransaction::class);

            $fromWallet = $fromWalletable->wallet($fromWalletSlug)->lockForUpdate()->first();
            $toWallet = $this->getOrCreateWallet($toWalletable, true, $toWalletSlug);

            if (!$fromWallet) {
                throw new WalletNotFoundException("Sender wallet '{$fromWalletSlug}' not found");
            }

            if ($fromWallet->id === $toWallet->id) {
                throw new InvalidAmountException('Cannot transfer to the same wallet');
            }

            $payloadHash = null;
            if ($idempotencyKey !== null) {
                $payload = [
                    'action'         => 'transfer',
                    'from_wallet_id' => (int) $fromWallet->id,
                    'to_wallet_id'   => (int) $toWallet->id,
                    'amount'         => (string) number_format($amount, 4, '.', ''),
                    'description'    => $description,
                    'reference_type' => $reference?->getMorphClass(),
                    'reference_id'   => $reference ? (string) $reference->getKey() : null,
                ];
                $payloadHash = $this->calculatePayloadHash($payload);

                $existing = $transactionModelClass::where('wallet_id', $fromWallet->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    if ($existing->payload_hash && $existing->payload_hash !== $payloadHash) {
                        throw new IdempotencyConflictException(
                            "Idempotency key '{$idempotencyKey}' was already used with a different payload."
                        );
                    }
                    return;
                }
            }

            $this->ensureSufficientBalance($fromWallet, $amount);
            $transactionNumber = $this->generateRandomTransactionNumber();

            // Update sender wallet
            $fromBalanceBefore = (float) $fromWallet->balance;
            $fromWallet->decrement('balance', $amount);
            $fromBalanceAfter = $fromBalanceBefore - $amount;

            $debitTransaction = $this->recordTransaction(new RecordTransactionData(
                wallet: $fromWallet,
                amount: -$amount,
                type: 'debit',
                description: $description,
                toWalletId: (int) $toWallet->id,
                fromWalletId: (int) $fromWallet->id,
                transactionNumber: $transactionNumber,
                balanceBefore: $fromBalanceBefore,
                balanceAfter: $fromBalanceAfter,
                reference: $reference,
                meta: $meta,
                idempotencyKey: $idempotencyKey,
                payloadHash: $payloadHash
            ));

            // Update receiver wallet
            $toBalanceBefore = (float) $toWallet->balance;
            $toWallet->increment('balance', $amount);
            $toBalanceAfter = $toBalanceBefore + $amount;

            $creditTransaction = $this->recordTransaction(new RecordTransactionData(
                wallet: $toWallet,
                amount: $amount,
                type: 'credit',
                description: $description,
                toWalletId: (int) $toWallet->id,
                fromWalletId: (int) $fromWallet->id,
                transactionNumber: $transactionNumber,
                balanceBefore: $toBalanceBefore,
                balanceAfter: $toBalanceAfter,
                reference: $reference,
                meta: $meta,
                idempotencyKey: $idempotencyKey ? $idempotencyKey . '-credit' : null,
                payloadHash: $payloadHash
            ));

            $fromWalletable->unsetRelation('wallet');
            $fromWalletable->unsetRelation('wallets');
            $toWalletable->unsetRelation('wallet');
            $toWalletable->unsetRelation('wallets');

            event(new WalletTransferCompleted($debitTransaction, $creditTransaction));
        });
    }

    public function refund(
        Model|int $transaction,
        ?float $amount = null,
        ?string $description = null
    ): Model {
        $transactionModelClass = config('easy-wallet.models.transaction', WalletTransaction::class);

        if (!$transaction instanceof Model) {
            $transaction = $transactionModelClass::findOrFail($transaction);
        }

        if ($transaction->is_refunded) {
            throw new TransactionAlreadyRefundedException('Transaction has already been fully refunded.');
        }

        $maxRefundable = abs((float) $transaction->amount) - (float) $transaction->refunded_amount;

        if ($amount === null) {
            $amount = $maxRefundable;
        }

        if ($amount <= 0 || $amount > $maxRefundable) {
            throw new InvalidRefundAmountException(
                "Invalid refund amount {$amount}. Maximum refundable is {$maxRefundable}."
            );
        }

        return DB::transaction(function () use ($transaction, $amount, $description) {
            $wallet = $transaction->wallet()->lockForUpdate()->first();

            if (!$wallet) {
                throw new WalletNotFoundException('Wallet associated with transaction not found');
            }

            $transactionNumber = $this->generateRandomTransactionNumber();
            $balanceBefore = (float) $wallet->balance;

            // If original transaction was debit, refund credits money back
            // If original transaction was credit, refund debits money back
            if ($transaction->type === 'debit') {
                $wallet->increment('balance', $amount);
                $balanceAfter = $balanceBefore + $amount;
                $refundAmount = $amount;
            } else {
                $this->ensureSufficientBalance($wallet, $amount);
                $wallet->decrement('balance', $amount);
                $balanceAfter = $balanceBefore - $amount;
                $refundAmount = -$amount;
            }

            $refundTransaction = $this->recordTransaction(new RecordTransactionData(
                wallet: $wallet,
                amount: $refundAmount,
                type: 'refund',
                description: $description ?? "Refund for transaction {$transaction->transaction_number}",
                transactionNumber: $transactionNumber,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                parentTransactionId: (int) $transaction->id
            ));

            $newRefundedAmount = (float) $transaction->refunded_amount + $amount;
            $transaction->update([
                'refunded_amount' => $newRefundedAmount,
                'is_refunded' => $newRefundedAmount >= abs((float) $transaction->amount),
            ]);

            $wallet->walletable?->unsetRelation('wallet');
            $wallet->walletable?->unsetRelation('wallets');

            event(new WalletRefunded($refundTransaction, $transaction));

            return $refundTransaction;
        });
    }

    public function balance(Model $walletable, string $wallet = 'default'): float
    {
        return (float) ($walletable->wallet($wallet)->value('balance') ?? 0);
    }

    private function calculatePayloadHash(array $payload): string
    {
        ksort($payload);
        return hash('sha256', (string) json_encode($payload));
    }

    private function getOrCreateWallet(Model $walletable, bool $lock = false, string $slug = 'default'): Model
    {
        $query = $walletable->wallet($slug);
        $wallet = $lock ? $query->lockForUpdate()->first() : $query->first();

        return $wallet ?? $walletable->wallets()->create(['slug' => $slug]);
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
