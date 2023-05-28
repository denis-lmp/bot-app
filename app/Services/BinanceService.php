<?php

namespace App\Services;

use App\Models\CryptoTrading;
use App\Models\CryptoTradingBot;
Use App\Models\User;
use Binance\API;
Use Auth;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use \App\Notifications\TelegramNotification;

class BinanceService
{
    protected API $api;
    protected array $allowedCrypto = ['BTCUSDT'];

    public function __construct()
    {
        $this->api = new API(Env::get('BINANCE_API_KEY'), Env::get('BINANCE_SECRET'));
    }

    /**
     * @throws Exception
     */
    public function getCheckTradingBids(): array
    {
        //Get all prices via the API
        $ticker = $this->api->prices();

        $trades = array();

        $cryptoTrading = new CryptoTrading();

        //Get all the trades actually sent
        $allTradesMade = $cryptoTrading->orderBy('created_at', 'DESC')->get();
        $trades['allTrades'] = $allTradesMade;

        //Go through each ticker and save price
        foreach ($ticker as $name => $value) {
            //Check if we are working with the Ticker
            if (in_array($name, $this->allowedCrypto)) {

                $traded             = false;
                $percentChange      = 0;
                $oldPrice           = 0;
                $percentChangeTrade = 0;

                //Get last trade made
                $lastTradeMade       = $cryptoTrading->orderBy('created_at', 'DESC')->first();

                $tradeArray          = array();
                $cryptoTradingBotGet = new CryptoTradingBot;

                $oldTrades = $cryptoTradingBotGet->where('ticker', $name)->whereBetween('created_at',
                    [Carbon::now()->subMinutes(1)->toDateTimeString(), Carbon::now()])->orderBy('id')->first();
                    // $oldTrades = $cryptoTradingBotGet->where('ticker', $name)->whereBetween('created_at',
                    // [Carbon::now()->subHour(1)->toDateTimeString(), Carbon::now()])->orderBy('id')->first();

                //Check we have some trades in the system
                if ($oldTrades) {
                    //Get the percentage change between now and 10 mins ago
                    $oldPrice      = $oldTrades->price;
                    $decreaseValue = $value - $oldTrades->price;
                    $percentChange = round(($decreaseValue / $oldTrades->price) * 100, 2);
                }
                // dd($oldTrades, $percentChange);
                //Here we will check if last was a buy and if so show percentage increase
                $lastTradeType = '';
                if (isset($trades['allTrades'][0])) {
                    $lastTradeType = $trades['allTrades'][0]['buy_sell'];

                }
                
                if ($lastTradeType == 'BUY') {
                    //Get the name
                    $lastTradeTicker = $trades['allTrades'][0]['ticker'];

                    //check if this iteration is the same ticker

                    if ($lastTradeTicker == $name) {
                        //Update the live trade for buys to show how much up/down
                        $decreaseValue                     = $value - $trades['allTrades'][0]['price'];
                        $percentChangeTrade                = round(($decreaseValue / $trades['allTrades'][0]['price']) * 100,
                            2);
                        // dd($percentChangeTrade, $trades['allTrades'][0]['price'], $value);
                        $trades['allTrades'][0]['current'] = $percentChangeTrade;
                        // User::find(1)->notify(new TelegramNotification('percentChangeTrade vs last buy' . $trades['allTrades'][0]['current']));
                    }
                }

                if ($lastTradeMade && $lastTradeMade->order_id != '123456') {
                    if ($this->checkIfLastOrderProcessed($lastTradeMade, $percentChangeTrade)) {
                        //Check latest prices and make decision to sell

                        $traded = $this->checkLastTradeAndProcessToSell($lastTradeMade, $name, $value);

                        //Check if trade has happened and buy
                        if (!$traded) {
                            $traded = $this->checkLastTradeAndProcessToBuy($lastTradeMade, $percentChange, $name, $value);
                        }

                        //Create something to force sell if one is over 5% in 10 minutes
                        //Force Sell and buy immediately
                    }
                }

                //Create Ticker
                $tradeArray['ticker']   = $name;
                $tradeArray['gain']     = $percentChange . '%';
                $tradeArray['oldValue'] = $oldPrice;
                $tradeArray['latest']   = $value;

                if (!$traded) {
                    $tradeArray['traded'] = 'No';
                } else {
                    $tradeArray['traded'] = 'Yes';
                }

                //Push to array
                array_push($trades, $tradeArray);

                //Save the latest Trade
                $cryptoTradingBot         = new CryptoTradingBot();
                $cryptoTradingBot->ticker = $name;
                $cryptoTradingBot->price  = $value;
                $cryptoTradingBot->percentage_change = $percentChangeTrade;
                $cryptoTradingBot->save();
            }
        }

        // User::find(1)->notify(new TelegramNotification(json_encode($trades[0])));

        return $trades;
    }

    /**
     * @throws Exception
     */
    private function checkIfLastOrderProcessed($lastTradeMade, $percentChangeTrade): bool
    {
        //Check if the status is filled of the last job
        if ($lastTradeMade->status == 'FILLED') {
            return true;
        }

        //If not filled check the system
        $orderstatus = $this->api->orderStatus($lastTradeMade->ticker, $lastTradeMade->order_id);

        //If filled then we can carry on
        if ($orderstatus['status'] == 'FILLED') {
            //If filled mark as filled and save
            $lastTradeMade->status = 'FILLED';
            $lastTradeMade->save();

            return true;
        } else {
            //If checks over 500 then delete the old order and carry on
            if (((Carbon::now()->subMinutes(10)->toDateTimeString() > $lastTradeMade->created_at) &&
                 ($percentChangeTrade < -5.00)) ||
                ($percentChangeTrade > 5.00) ||
                $lastTradeMade->checks > 10000) {
                $response = $this->api->cancel($lastTradeMade->ticker, $lastTradeMade->order_id);
                $lastTradeMade->delete();

                return true;
            }

            $lastTradeMade->status = 'PROCESSING';
            $lastTradeMade->save();

            //If not add one more check for filled
            $lastTradeMade->checks = (int)$lastTradeMade->checks + 1;
            $lastTradeMade->save();

        }

        //Return false as we still have an order
        return false;
    }

    public function checkLastTradeAndProcessToSell($lastTradeMade, $name, $value): void
    {
        if ($lastTradeMade->ticker == $name && $lastTradeMade->buy_sell == 'BUY') {
            // //Get last trade to make sure it's still a buy to sell to avoid duops
            DB::transaction(function () use ($lastTradeMade, $name, $value) {

                $cryptoTrading = new CryptoTrading();
                //Get the percentage change between now and 10 mins ago
                // var_dump('last price = ' . $lastTradeMade->price);
                // var_dump('now price = ' . $value);

                $decreaseValue = $value - (float)$lastTradeMade->price;
                $percentChange = round(($decreaseValue / $value) * 100, 2);
                // var_dump('percentage change = ' . $percentChange, $name);
                // dd($percentChange);
                if ($percentChange >= 1.00 || $percentChange < -5.00) {

                    // $tradeName = $this->returnReverseTickerName($lastTradeMade);

                    // //Get last trade to make sure it's still a buy to sell to avoid duops
                    $lastTradeMadeLive = CryptoTrading::orderBy('created_at', 'DESC')->first();
                    // dd($lastTradeMadeLive);

                    if ($lastTradeMadeLive->buy_sell == 'BUY') {
                        $quantity          = $this->getSellBalance(); // balance of BTC by default
                        // $quantity        = $this->calculateAvailableBuyQuantity();
                        $quantity = bcdiv((float)$quantity, 1, 4);
                        // dd($quantity);
                        // $convertToString = (string)$quantity;
                        // $decimalLocation = strpos($convertToString, '.');
                        // $quantityStart   = substr($quantity, 0, $decimalLocation);
                        // $quantityEnd     = substr($quantity, $decimalLocation, 3);
                        // $quantity        = $quantityStart . $quantityEnd;
                        
                        //Sell Now
                        // dd(env('APP_ENV'));
                        // if (env('APP_ENV') != 'local') {
                            // dd(123);
                            if ($percentChange < -5.00 || $percentChange > 5.00) {
                                User::find(1)->notify(new TelegramNotification('Market sell if %', $percentChange, $quantity, $value));
                                // $order = $this->api->marketSell($lastTradeMade->ticker, $quantity);
                                // dd($lastTradeMade->ticker, $quantity, $value);
                                $order = $this->api->sell($lastTradeMade->ticker, $quantity, $value);
                            } else {
                                User::find(1)->notify(new TelegramNotification('Sell if %', $quantity, $value));
                                $order = $this->api->sell($lastTradeMade->ticker, $quantity, $value);
                            }

                            //$order = $this->api->marketSell($lastTradeMade->ticker, $quantity);
                            // //signedRequest error: {"code":-1013,"msg":"Filter failure: LOT_SIZE"}
                            // //check for lot size error
                            // //and put it floor
                            // if (isset($order['code']) && $order['code'] == '-1013') {
                            //     $quantity = 0.0003700;
                            //     // $quantity = floor($quantity);
                            //     if ($percentChange < -5.00 || $percentChange > 5.00) {
                            //         $order = $this->api->sell($lastTradeMade->ticker, $quantity, $value);

                            //         User::find(1)->notify(new TelegramNotification('Sell', $percentChange, $quantity, $lastTradeMade->ticker));
                            //         // $order = $this->api->marketSell($lastTradeMade->ticker, $quantity);
                            //     }
                            //     // } else {
                            //     //     User::find(1)->notify(new TelegramNotification('Sell ', $quantity, $lastTradeMade->ticker));

                            //     //     // $order = $this->api->sell($lastTradeMade->ticker, $quantity, $value);
                            //     // }
                            //     //$order = $this->api->marketSell($lastTradeMade->ticker, $quantity);
                            // }

                            if (!isset($order['code'])) {
                                if (!isset($order['orderId'])) {
                                    $orderId = '';
                                } else {
                                    $orderId = $order['orderId'];
                                }
                            }
                        // } else {
                        //     $orderId = '123456';
                        //     return false;
                        // }

                        if (isset($orderId)) {
                            $cryptoTrading->ticker    = $name;
                            $cryptoTrading->price     = $value;
                            $cryptoTrading->old_price = $lastTradeMade->price;
                            $cryptoTrading->buy_sell  = 'SELL';
                            $cryptoTrading->amount    = $quantity;
                            $cryptoTrading->order_id  = $orderId;
                            $cryptoTrading->checks    = (int)$lastTradeMade->checks + 1;
                            $cryptoTrading->save();

                            $cryptoTradingBot = new CryptoTradingBot();
                            $cryptoTradingBot->where('ticker', $name)->delete();

                            return false;
                        }
                    }
                }
            });
        }
    }

    private function returnReverseTickerName($lastTradeMade, $justTickerName = false): string
    {
        $ticker = 'SCBTC';

        if (isset($lastTradeMade->ticker)) {
            $ticker = $lastTradeMade->ticker;
        } else {
            if ($justTickerName) {
                $ticker = $lastTradeMade;
            }
        }

        //Check if ticker is 2,3 or 4 lenght ticker
        if (substr($ticker, 3, 4) == 'BTC') {
            //Switch around Name to get back to BTC from 3 char ticker
            $firstTicker  = substr($ticker, 0, 3);
            $secondTicker = substr($ticker, 3, 4);
        } else {
            if (substr($ticker, 2, 4) == 'BTC') {
                //Switch around Name to get back to BTC from 2 char ticker
                $firstTicker  = substr($ticker, 0, 2);
                $secondTicker = substr($ticker, 2, 4);
            } else {
                //Switch around Name SALTBTC to get back to BTC 4 char ticker
                $firstTicker  = substr($ticker, 0, 4);
                $secondTicker = substr($ticker, 4, 6);
            }
        }

        //Trade Name
        $tradeName = $secondTicker . $firstTicker;

        if ($justTickerName) {
            return $firstTicker;
        }

        return $tradeName;
    }

    public function checkLastTradeAndProcessToBuy($lastTradeMade, $percentageIncrease, $name, $value): void
    {
        // var_dump('BUY');
        // dd($lastTradeMade->buy_sell, $percentageIncrease);
        // if ($lastTradeMade->buy_sell == 'SELL' && $percentageIncrease <= -1.00) {
        if ($lastTradeMade->buy_sell == 'SELL') {
            // dd(124);
            // //Get last trade to make sure it's still a buy to sell to avoid duops

            DB::transaction(function () use ($lastTradeMade, $percentageIncrease, $name, $value) {
                $cryptoTrading     = new CryptoTrading;
                // $btcBalance        = $this->getBTCBalance();
                $usdtBalance = $this->getUSDTBalance();

                $cryptoTradingLive = new CryptoTrading;
                $lastTradeMadeLive = $cryptoTradingLive->orderBy('created_at', 'DESC')->first();
                if ($lastTradeMadeLive->buy_sell == 'SELL') {
                    //Buy Now

                    $quantity = (float) $usdtBalance / (float)$value;
                    $quantity = bcdiv((float)$quantity, 1, 5);

                    if ($quantity != 0) {
                        if (env('APP_ENV') != 'local') {
                            User::find(1)->notify(new TelegramNotification('BUY if %', $percentageIncrease, $quantity, $value));
                            $order = $this->api->buy($name, $quantity, $value);

                            // $order = $this->api->marketBuy($name, $quantity);
                        } else {
                            $order            = array();
                            $order['orderId'] = '123456';
                        }

                        if (isset($order['orderId'])) {
                            $cryptoTrading->ticker    = $name;
                            $cryptoTrading->price     = $value;
                            $cryptoTrading->old_price = '';
                            $cryptoTrading->buy_sell  = 'BUY';
                            $cryptoTrading->amount    = $quantity;
                            $cryptoTrading->order_id  = $order['orderId'];
                            $cryptoTrading->checks = 1;
                            $cryptoTrading->save();

                            return true;
                        }
                    }
                }
            });
        }
    }

    /**
     * @throws Exception
     */
    private function getBTCBalance()
    {
        return $this->api->price('BTCUSDT');
    }

    private function getUSDTBalance()
    {
        $balances = $this->api->balances('USDT');
        $balance = $balances['USDT']['available'] ?? null;

        return round((float) $balance, 3);
        
    }

    /**
     * Check for profile balance
     */
    public function getSellBalance(string $name = 'BTC')
    {
        $balances = $this->api->balances($name);

        if (isset($balances[$name])) {
            $quantity = $balances[$name]['available'] ?? null;
            return round((float) $quantity, 8);
        }

        return 'Balance checking error' . $balances;
    }

    /**
     * Check for price
     */
    public function getPrice(string $name = 'BTCUSDT')
    {
        return $this->api->price($name);
    }

    public function getOrders(string $name = 'BTCUSDT')
    {
        return json_encode($this->api->orders($name));
    }

    /**
     * Test sell
     */
    public function testSell(string $ticker = 'BTCUSDT', int $value = 0)
    {
        $quantity = 0.00047;

        // $info = $this->api->exchangeInfo();
        // dd($info['symbols'][$this->allowedCrypto[0]]);


        $tickers = $this->api->prices();

        $currentPrice = $tickers[$this->allowedCrypto[0]] ?? null;
        $quantity = sprintf("%.6f", $quantity);

        return $this->api->sell($ticker, $quantity, $currentPrice);
    }

    /**
     *  Test buy
     */
    public function testBuy(string $ticker = 'BTCUSDT', int $value = 0)
    {
        $q = $this->calculateAvailableBuyQuantity();
        $quantity = sprintf("%.6f", $q[0]);

        $order = $this->api->buy($ticker, $quantity, $q[1] ?? 0);

        return $order;
    }

    private function calculateAvailableBuyQuantity()
    {
        $usdtBalance = $this->getUSDTBalance('USDT');
        if (isset($usdtBalance['USDT'])) {
            $usdtBalance = $usdtBalance['USDT']['available'];
        }

        $tickers = $this->api->prices();
        $currentPrice = $tickers[$this->allowedCrypto[0]] ?? null;
        $quantity = round((float) $usdtBalance / (float) $currentPrice, 5);

        return [$quantity ?? null, $currentPrice];
    }

    public function exportOrdersToDatabase()
    {
        $orders = $this->getOrders();
        $orders = json_decode($orders);
        foreach ($orders as $key => $order) {
            $keyPast = $key - 1 ?? 0;
            $ct = new CryptoTrading();
            $ct->order_id = $order->orderId;
            $ct->ticker = $order->symbol ?? '';
            $ct->amount = $order->executedQty;
            $ct->price = $order->price;
            $ct->old_price = $orders[$keyPast]->price ?? 'BTCUSDT';
            $ct->buy_sell = $order->side;
            $ct->order_id = $order->orderId;
            $ct->status = $order->status;
            $ct->checks = 1;
            $ct->save();

        }

        return json_encode('Success.');

    }
}