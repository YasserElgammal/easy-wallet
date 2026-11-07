<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use YasserElgammal\LaravelEasyWallet\Models\WalletTransaction;

class WalletDebited
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public WalletTransaction $transaction;

    public function __construct(WalletTransaction $transaction)
    {
        $this->transaction = $transaction;
    }
}
