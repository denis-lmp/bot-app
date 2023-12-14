<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 14/12/2023
 * Time: 13:04
 */

namespace App\Repositories;

use App\Repositories\Contracts\AbstractEloquentRepository;
use App\Repositories\Contracts\CryptoTradingRepositoryInterface;

class CryptoTradingBotRepository extends AbstractEloquentRepository implements CryptoTradingRepositoryInterface
{


    public function __construct()
    {
    }

    public function getForPeriod($ticker, $period): mixed
    {
        return $this->getRowsForDateRange($ticker, $period);
    }
}