<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Data;

use Illuminate\Database\Eloquent\Model;

class RecordTransactionData
{
    public function __construct(
        public Model $wallet,
        public float $amount,
        public string $type,
        public ?string $description = null,
        public ?int $toWalletId = null,
        public ?int $fromWalletId = null,
        public ?string $transactionNumber = null,
        public ?float $balanceBefore = null,
        public ?float $balanceAfter = null,
        public ?Model $reference = null,
        public ?array $meta = null,
        public ?string $idempotencyKey = null,
        public ?string $payloadHash = null,
        public ?int $parentTransactionId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'wallet_id'             => $this->wallet->id,
            'type'                  => $this->type,
            'amount'                => $this->amount,
            'description'           => $this->description,
            'to_wallet_id'          => $this->toWalletId,
            'from_wallet_id'        => $this->fromWalletId,
            'transaction_number'    => $this->transactionNumber,
            'balance_before'        => $this->balanceBefore,
            'balance_after'         => $this->balanceAfter,
            'reference_type'        => $this->reference?->getMorphClass(),
            'reference_id'          => $this->reference?->getKey(),
            'meta'                  => $this->meta,
            'idempotency_key'       => $this->idempotencyKey,
            'payload_hash'          => $this->payloadHash,
            'parent_transaction_id' => $this->parentTransactionId,
        ];
    }
}
