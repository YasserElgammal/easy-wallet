<?php

namespace YasserElgammal\LaravelEasyWallet\Facades;

use Illuminate\Support\Facades\Facade;

class EasyWallet extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */

    protected static function getFacadeAccessor()
    {
        return 'easy-wallet';
    }
}
