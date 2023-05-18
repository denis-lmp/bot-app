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

                //Check we have some trades in the system
                if ($oldTrades) {
                    //Get the percentage change between now and 10 mins ago
                    $oldPrice      = $oldTrades->price;
                    $decreaseValue = $value - $oldTrades->price;
                    $percentChange = round(($decreaseValue / $oldTrades->price) * 100, 2);
                }
                //Here we will check if last was a buy and if so show percentage increase
                $lastTradeType = '';
                if (isset($trades['allTrades'][0])) {
                    $lastTradeType = $trades['allTrades'][0]['buy_sell'];

                }
                
                if ($lastTradeType == 'buy') {
                    //Get the name
                    $lastTradeTicker = $trades['allTrades'][0]['ticker'];

                    //check if this iteration is the same ticker

                    if ($lastTradeTicker == $name) {
                        //Update the live trade for buys to show how much up/down
                        $decreaseValue                     = $value - $trades['allTrades'][0]['price'];
                        $percentChangeTrade                = round(($decreaseValue / $trades['allTrades'][0]['price']) * 100,
                            2);

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
                $cryptoTradingBot->save();
            }
        }

        User::find(1)->notify(new TelegramNotification(json_encode($trades[0])));

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
                $lastTradeMade->checks > 20000) {
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
        // var_dump('ProcessToSell');
        if ($lastTradeMade->ticker == $name && $lastTradeMade->buy_sell == 'buy') {
            // //Get last trade to make sure it's still a buy to sell to avoid duops
            DB::transaction(function () use ($lastTradeMade, $name, $value) {

                $cryptoTrading = new CryptoTrading();
                //Get the percentage change between now and 10 mins ago
                // var_dump('last price = ' . $lastTradeMade->price);
                // var_dump('now price = ' . $value);

                $decreaseValue = $value - (float)$lastTradeMade->price;
                $percentChange = round(($decreaseValue / $value) * 100, 2);

                // var_dump('percentage change = ' . $percentChange, $name);
         
                if ($percentChange >= 1.00 || $percentChange < -5.00) {
                    $tradeName = $this->returnReverseTickerName($lastTradeMade);

                    // //Get last trade to make sure it's still a buy to sell to avoid duops
                    $lastTradeMadeLive = CryptoTrading::orderBy('created_at', 'DESC')->first();

                    if ($lastTradeMadeLive->buy_sell == 'buy') {
                        $amount          = $this->getSellBalance(); // balance of BTC by default
                        $quantity        = ($amount['available'] - (0.050 * $amount['available']) / 100);
                        $convertToString = (string)$quantity;
                        $decimalLocation = strpos($convertToString, '.');
                        $quantityStart   = substr($quantity, 0, $decimalLocation);
                        $quantityEnd     = substr($quantity, $decimalLocation, 3);
                        $quantity        = $quantityStart . $quantityEnd;

                        //Sell Now
                        if (env('APP_ENV') != 'local') {
                            if ($percentChange < -5.00 || $percentChange > 5.00) {
                                User::find(1)->notify(new TelegramNotification('Market sell if %', $percentChange, $quantity, $value));
                                // $order = $this->api->marketSell($lastTradeMade->ticker, $quantity);
                            } else {
                                User::find(1)->notify(new TelegramNotification('Sell if %', $quantity, $value));
                                // $order = $this->api->sell($lastTradeMade->ticker, $quantity, $value);
                            }

                            //$order = $this->api->marketSell($lastTradeMade->ticker, $quantity);
                            //signedRequest error: {"code":-1013,"msg":"Filter failure: LOT_SIZE"}
                            //check for lot size error
                            //and put it floor
                            if (isset($order['code']) && $order['code'] == '-1013') {
                                $quantity = floor($quantity);
                                if ($percentChange < -5.00 || $percentChange > 5.00) {
                                    User::find(1)->notify(new TelegramNotification('Market sell -1013 %', $percentChange, $quantity, $lastTradeMade->ticker));
                                    // $order = $this->api->marketSell($lastTradeMade->ticker, $quantity);
                                } else {
                                    User::find(1)->notify(new TelegramNotification('Sell ', $quantity, $lastTradeMade->ticker));

                                    // $order = $this->api->sell($lastTradeMade->ticker, $quantity, $value);
                                }
                                //$order = $this->api->marketSell($lastTradeMade->ticker, $quantity);
                            }

                            if (!isset($order['code'])) {
                                if (!isset($order['orderId'])) {
                                    $orderId = '';
                                } else {
                                    $orderId = $order['orderId'];
                                }
                            }
                        } else {
                            $orderId = '123456';
                            return false;
                        }

                        if (isset($orderId)) {
                            $cryptoTrading->ticker    = $name;
                            $cryptoTrading->price     = $value;
                            $cryptoTrading->old_price = $lastTradeMade->price;
                            $cryptoTrading->buy_sell  = 'sell';
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
        if ($lastTradeMade->buy_sell == 'sell' && $percentageIncrease <= -1.00) {
            // //Get last trade to make sure it's still a buy to sell to avoid duops

            DB::transaction(function () use ($lastTradeMade, $percentageIncrease, $name, $value) {

                $cryptoTrading     = new CryptoTrading;
                $btcBalance        = $this->getBTCBalance();
                $cryptoTradingLive = new CryptoTrading;
                $lastTradeMadeLive = $cryptoTradingLive->orderBy('created_at', 'DESC')->first();

                if ($lastTradeMadeLive->buy_sell == 'sell') {
                    //Buy Now
                    $quantity = (float)$btcBalance / (float)$value;
                    $quantity = floor($quantity);

                    if ($quantity != 0) {
                        if (env('APP_ENV') != 'local') {
                            $order = $this->api->buy($name, $quantity, $value);

                            $order = $this->api->marketBuy($name, $quantity);
                        } else {
                            $order            = array();
                            $order['orderId'] = '123456';
                        }

                        if (isset($order['orderId'])) {
                            $cryptoTrading->ticker    = $name;
                            $cryptoTrading->price     = $value;
                            $cryptoTrading->old_price = '';
                            $cryptoTrading->buy_sell  = 'buy';
                            $cryptoTrading->amount    = $quantity;
                            $cryptoTrading->order_id  = $order['orderId'];
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
    private function getBTCBalance(): array
    {
        return $this->api->price('USDTBTC');
    }

    /**
     * Check for profile balance
     */
    public function getSellBalance(string $name = 'BTC')
    {
        $balances = $this->api->balances($name);
        
        if (isset($balances[$name])) {
            return $balances[$name];
        }

        return 'Balance checking error' . $balances;
    }

    /**
     * Check for price
     */
    public function getPrice(string $name = 'BTCUSDT')
    {
        return ['BTCUSDT' => $this->api->price($name)];
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
        $quantity = 0.0003700;

        // $info = $this->api->exchangeInfo();
        // dd($info['symbols'][$this->allowedCrypto[0]]);


        $tickers = $this->api->prices();

        $currentPrice = $tickers[$this->allowedCrypto[0]] ?? null;
        $quantity = sprintf("%.6f", $quantity);
        $order = $this->api->sell($ticker, $quantity, $currentPrice);

        return $order;
    }

    /**
     *  Test buy
     */
    public function testBuy(string $ticker = 'BTCUSDT', int $value = 0)
    {
        $quantity = 0.0003700;

        // $info = $this->api->exchangeInfo();
        // dd($info['symbols'][$this->allowedCrypto[0]]);
        $tickers = $this->api->prices();

        $currentPrice = $tickers[$this->allowedCrypto[0]] ?? null;
        
        // dd($currentPrice * 0.2, $currentPrice, $currentPrice * 1.2);
        $quantity = sprintf("%.6f", $quantity);

        $order = $this->api->buy($ticker, $quantity, $currentPrice);

        return $order;
    }
}