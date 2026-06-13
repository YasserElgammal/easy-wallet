<?php

declare(strict_types=1);

namespace YasserElgammal\LaravelEasyWallet\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphOne;

interface Walletable
{
    /**
     * Get the wallet relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function wallet(): MorphOne;
}
