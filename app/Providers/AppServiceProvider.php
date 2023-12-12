<?php

namespace App\Providers;

use App\Repositories\Contracts\CryptoTradingRepositoryInterface;
use App\Repositories\Contracts\CryptoTradingServiceInterface;
use App\Repositories\CryptoTradingRepository;
use App\Services\CryptoTradingService;
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
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);

        }

        $this->app->bind(CryptoTradingRepositoryInterface::class, CryptoTradingRepository::class);
        $this->app->bind(CryptoTradingServiceInterface::class, CryptoTradingService::class);
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
