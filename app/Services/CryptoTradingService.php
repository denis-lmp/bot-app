<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 11/12/2023
 * Time: 12:56
 */

namespace App\Services;

use App\Repositories\Contracts\CryptoTradingRepositoryInterface;
use App\Repositories\Contracts\CryptoTradingServiceInterface;
use App\Repositories\CryptoTradingRepository;

class CryptoTradingService implements CryptoTradingServiceInterface
{
    protected CryptoTradingRepository $cryptoTradingRepository;

    public function __construct(CryptoTradingRepositoryInterface $cryptoTradingRepository)
    {
        $this->cryptoTradingRepository = $cryptoTradingRepository;
    }

    public function getTradingForPeriod($ticker, $period): mixed
    {
        return $this->cryptoTradingRepository->getForPeriod($ticker, $period);
    }

    public function getTrading($id): mixed
    {
        return $this->cryptoTradingRepository->findOne($id);
    }
}