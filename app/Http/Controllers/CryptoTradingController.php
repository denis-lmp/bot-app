<?php

namespace App\Http\Controllers;

use App\Http\Requests\CryptoTrading\CryptoTradingStoreRequest;
use App\Http\Resources\CryptoTradingResource;
use App\Models\CryptoTrading;
use App\Repositories\Contracts\CryptoTradingServiceInterface;
use App\Services\CryptoTradingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CryptoTradingController extends Controller
{
    protected CryptoTradingService $cryptoTradingService;

    public function __construct(CryptoTradingServiceInterface $cryptoTradingService)
    {
        $this->cryptoTradingService = $cryptoTradingService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return CryptoTradingResource
     */
    public function index(): CryptoTradingResource
    {
        $ticker = ['ticker' => 'BTCUSDT'];
        $period = 'current_month';

        $result = $this->cryptoTradingService->getTradingForPeriod($ticker, $period);


        return new CryptoTradingResource($result);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(CryptoTradingStoreRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return CryptoTradingResource
     */
    public function show($id): CryptoTradingResource
    {
        $result = $this->cryptoTradingService->getTrading($id);

        return new CryptoTradingResource($result);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  CryptoTrading  $cryptoTrading
     * @return Response
     */
    public function edit(CryptoTrading $cryptoTrading)
    {

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  CryptoTrading  $cryptoTrading
     * @return Response
     */
    public function update(Request $request, CryptoTrading $cryptoTrading)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  CryptoTrading  $cryptoTrading
     * @return Response
     */
    public function destroy(CryptoTrading $cryptoTrading)
    {
        //
    }
}
