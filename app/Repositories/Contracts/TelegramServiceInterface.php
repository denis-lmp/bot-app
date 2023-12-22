<?php

namespace App\Repositories\Contracts;

interface TelegramServiceInterface
{
    public function sendTradings($tradings);
}