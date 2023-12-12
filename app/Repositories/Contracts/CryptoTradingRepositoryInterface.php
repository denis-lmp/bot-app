<?php

namespace App\Repositories\Contracts;

interface CryptoTradingRepositoryInterface extends BaseRepository
{
    public function getForPeriod($ticker, $period): mixed;
}