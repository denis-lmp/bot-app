<?php

namespace App\Http\Controllers;

use App\Http\Resources\CryptoTradingBotResource;
use App\Models\CryptoTradingBot;
use App\Repositories\CryptoTradingBotRepository;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriceController extends Controller
{
    protected CryptoTradingBotRepository $cryptoTradingBotRepo;

    public function __construct(CryptoTradingBotRepository $cryptoTradingBotRepository)
    {
        $this->cryptoTradingBotRepo = $cryptoTradingBotRepository;
    }

    public function getPrices(): AnonymousResourceCollection
    {
        $ticker = ['ticker' => 'BTCUSDT'];
        $period = 'current_week';

        $data = $this->cryptoTradingBotRepo->getForPeriod($ticker, $period);

        return CryptoTradingBotResource::collection($data);
    }
}
