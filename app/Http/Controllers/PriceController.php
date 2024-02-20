<?php

namespace App\Http\Controllers;

use App\Http\Resources\CryptoTradingBotResource;
use App\Models\CryptoTradingBot;
use App\Repositories\CryptoTradingBotRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriceController extends Controller
{
    protected CryptoTradingBotRepository $cryptoTradingBotRepo;

    public function __construct(CryptoTradingBotRepository $cryptoTradingBotRepository)
    {
        $this->cryptoTradingBotRepo = $cryptoTradingBotRepository;
    }

    public function getPrices(Request $request): AnonymousResourceCollection
    {
        $ticker = ['ticker' => 'BTCUSDT'];

        if ($request->has('startDate') || $request->has('endDate')) {
            $period[0] = $request->input('startDate');
            $period[1] = $request->input('endDate');
        } else {
            $period = 'current_week';
        }

        $data = $this->cryptoTradingBotRepo->getForPeriod($ticker, $period);

        return CryptoTradingBotResource::collection($data);
    }
}
