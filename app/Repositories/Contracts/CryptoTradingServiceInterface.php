<?php

namespace App\Repositories\Contracts;

interface CryptoTradingServiceInterface
{
    public function getTradingForPeriod($ticker, $period): mixed;

}