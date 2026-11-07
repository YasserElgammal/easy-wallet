<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use YasserElgammal\LaravelEasyWallet\Models\WalletTransaction;

class WalletTransferCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public WalletTransaction $debitTransaction;
    public WalletTransaction $creditTransaction;

    public function __construct(WalletTransaction $debitTransaction, WalletTransaction $creditTransaction)
    {
        $this->debitTransaction = $debitTransaction;
        $this->creditTransaction = $creditTransaction;
    }
}
