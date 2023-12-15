<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 14/12/2023
 * Time: 16:56
 */

namespace App\Jobs;

use App\Services\BinanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CryptoTradingBotCleaner implements ShouldQueue
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
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->binanceService->deleteRaws('BTCUSDT');
    }

}