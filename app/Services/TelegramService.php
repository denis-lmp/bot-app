<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 21/12/2023
 * Time: 12:43
 */

namespace App\Services;

use App\Jobs\SendTelegramNotification;
use App\Repositories\Contracts\TelegramServiceInterface;

class TelegramService implements TelegramServiceInterface
{
    public function sendTradings($tradings): void
    {
        SendTelegramNotification::dispatch($tradings);
    }
}