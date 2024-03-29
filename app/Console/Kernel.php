<?php

namespace App\Console;

use App\Jobs\CryptoBot;
use App\Jobs\CryptoTradingBotCleaner;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->job(app(CryptoBot::class))->everyMinute()->appendOutputTo(storage_path('logs/inspire.log'));

        $schedule->job(app(CryptoTradingBotCleaner::class))->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
