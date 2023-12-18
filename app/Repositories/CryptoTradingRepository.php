<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 11/12/2023
 * Time: 12:28
 */

namespace App\Repositories;

use App\Models\CryptoTrading;
use App\Repositories\Contracts\AbstractEloquentRepository;
use App\Repositories\Contracts\CryptoTradingRepositoryInterface;

class CryptoTradingRepository extends AbstractEloquentRepository implements CryptoTradingRepositoryInterface
{
    /**
     * @param  CryptoTrading  $cryptoTrading
     */
    public function __construct(CryptoTrading $cryptoTrading)
    {
        parent::__construct($cryptoTrading);
    }

    /**
     * @param $ticker
     * @param $period
     * @return mixed
     */
    public function getForPeriod($ticker, $period): mixed
    {
        return $this->getRowsForDateRange($ticker, $period);
    }

}