<?php

namespace App\Providers;

use App\Repositories\Contracts\BinanceServiceInterface;
use App\Repositories\Contracts\CryptoTradingRepositoryInterface;
use App\Repositories\Contracts\CryptoTradingServiceInterface;
use App\Repositories\Contracts\TelegramServiceInterface;
use App\Repositories\CryptoTradingRepository;
use App\Services\BinanceService;
use App\Services\CryptoTradingService;
use App\Services\TelegramService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(CryptoTradingRepositoryInterface::class, CryptoTradingRepository::class);
        $this->app->bind(CryptoTradingServiceInterface::class, CryptoTradingService::class);

        $this->app->bind(BinanceServiceInterface::class, BinanceService::class);
        $this->app->bind(TelegramServiceInterface::class, TelegramService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
