<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\BinanceServiceInterface;
use App\Services\BinanceService;
use Exception;

class ToolsController extends Controller
{
    /**
     * @var BinanceService|BinanceServiceInterface
     */
    protected BinanceService|BinanceServiceInterface $binanceService;

    /**
     * @param  BinanceServiceInterface  $binanceService
     */
    public function __construct(BinanceServiceInterface $binanceService)
    {
        $this->binanceService = $binanceService;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getCheckTradingBids(): array
    {
        return $this->binanceService->getCheckTradingBids();
    }

    /**
     *
     */
    public function getBalance(BinanceService $binanceService, $coin)
    {
        return $binanceService->getSellBalance($coin);
    }


    public function testSell(BinanceService $binanceService)
    {
        return $binanceService->testSell();
    }

    public function testBuy(BinanceService $binanceService)
    {
        return $binanceService->testBuy();
    }

    public function getPrice(BinanceService $binanceService)
    {
        return $binanceService->getPrice();
    }

    public function getOrders(BinanceService $binanceService)
    {
        return $binanceService->getOrders();
    }

    public function exportOrders(BinanceService $binanceService)
    {
        return $binanceService->exportOrdersToDatabase();
    }

}
