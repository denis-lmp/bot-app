<?php

namespace App\Jobs;

use App\Services\BinanceService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CryptoBot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private BinanceService $binanceService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BinanceService $binanceService)
    {
        $this->binanceService = $binanceService;
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        $this->getTradingBidsWithInterval(3, 18);
    }

    private function getTradingBidsWithInterval(int $iterations, int $interval): void
    {
        for ($i = 0; $i < $iterations; $i++) {
            rescue(/**
             * @throws Exception
             */ function () {
                return $this->binanceService->getCheckTradingBids();
            }, false);

            if ($i < $iterations - 1) {
                sleep($interval);
            }
        }
    }
}