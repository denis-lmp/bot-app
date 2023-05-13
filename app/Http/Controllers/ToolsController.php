<?php

namespace App\Http\Controllers;

use App\Services\BinanceService;
use Binance\API;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

class ToolsController extends Controller
{
    /**
     * @throws Exception
     */
    public function getCheckTradingBids(): array
    {
        return (new BinanceService())->getCheckTradingBids();
        // return $binanceService->getCheckTradingBids();
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

}
