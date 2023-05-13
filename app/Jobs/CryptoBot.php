<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\BinanceService;
use Carbon\Carbon;

class CryptoBot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $binance;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        app('App\Http\Controllers\ToolsController')->getCheckTradingBids($this->binance);
        sleep(18);
        app('App\Http\Controllers\ToolsController')->getCheckTradingBids($this->binance);
        sleep(18);
        app('App\Http\Controllers\ToolsController')->getCheckTradingBids($this->binance);
    }
}
