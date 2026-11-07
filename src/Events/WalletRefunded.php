<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletRefunded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Model $refundTransaction,
        public Model $originalTransaction
    ) {}
}

