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
    protected string $allowedCrypto = 'BTCUSDT';

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
        $allTradesMade = $cryptoTrading->orderBy('created_at', 'DESC')->first();
        $trades['allTrades'] = $allTradesMade;

        if (isset($ticker[$this->allowedCrypto])) {
            $name = $this->allowedCrypto;
            $value = $ticker[$this->allowedCrypto];

            $traded             = false;
            $percentChange      = 0;
            $oldPrice           = 0;
            $percentChangeTrade = 0;

            //Get last trade made
            $lastTradeMade = $cryptoTrading->orderBy('created_at', 'DESC')->first();

            $tradeArray = array();

            //Check we have some trades in the system
            if ($lastTradeMade) {
                //Get the percentage change between now and 10 mins ago
                $oldPrice      = $lastTradeMade->price;
                $decreaseValue = $value - $lastTradeMade->price;
                $percentChange = round(($decreaseValue / $lastTradeMade->price) * 100, 2);
            }

            //Here we will check if last was a buy and if so show percentage increase
            $lastTradeType = '';

            if (isset($trades['allTrades'])) {
                $lastTradeType = $trades['allTrades']->buy_sell;

            }

            if ($lastTradeMade && $lastTradeMade->order_id != '123456' && $lastTradeMade->buy_sell == 'BUY') {
                if ($this->checkIfLastOrderProcessed($lastTradeMade, $percentChange)) {
                    //Check latest prices and make decision to sell
                    $traded = $this->checkLastTradeAndProcessToSell($lastTradeMade, $name, $value);
                }
            } else {
                $traded = $this->checkLastTradeAndProcessToBuy($lastTradeMade, $percentChange, $name, $value);
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
            $cryptoTradingBot->percentage_change = $percentChange;
            $cryptoTradingBot->save();
        }

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
                $decreaseValue = $value - (float)$lastTradeMade->price;
                $percentChange = round(($decreaseValue / $value) * 100, 2);

                if ($percentChange >= 1.00 || $percentChange < -3.00) {
                    // $tradeName = $this->returnReverseTickerName($lastTradeMade);

                    // //Get last trade to make sure it's still a buy to sell to avoid duops
                    $lastTradeMadeLive = CryptoTrading::orderBy('created_at', 'DESC')->first();
                    // dd($lastTradeMadeLive);

                    if ($lastTradeMadeLive->buy_sell == 'BUY') {
                        $quantity          = $this->getSellBalance(); // balance of BTC by default
                        $quantity = bcdiv((float)$quantity, 1, 4);

                        User::find(1)->notify(new TelegramNotification('Sell if %'. $percentChange . ' ' . $quantity));
//                        $order = $this->api->sell($lastTradeMade->ticker, $quantity, $value);


                        if (!isset($order['code'])) {
                            if (!isset($order['orderId'])) {
                                $orderId = '';
                            } else {
                                $orderId = $order['orderId'];
                            }
                        }
                        $quantity = bcdiv((float)$quantity, 1, 4);
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
        if ($lastTradeMade->buy_sell == 'SELL') {
            // //Get last trade to make sure it's still a buy to sell to avoid duops

            DB::transaction(function () use ($lastTradeMade, $percentageIncrease, $name, $value) {

                $cryptoTrading     = new CryptoTrading;
                $usdtBalance = $this->getUSDTBalance();

                $cryptoTradingLive = new CryptoTrading;
                $lastTradeMadeLive = $cryptoTradingLive->orderBy('created_at', 'DESC')->first();

                if ($lastTradeMadeLive->buy_sell == 'SELL') {
                    if ($percentageIncrease < -1.00) {
                        $quantity = (float) $usdtBalance / (float)$value;
                        $quantity = bcdiv((float)$quantity, 1, 5);

                        if ($quantity != 0) {
                            if (env('APP_ENV') != 'local') {
                                User::find(1)->notify(new TelegramNotification('BUY if % '. $percentageIncrease . ' ' .  $quantity));
//                                $order = $this->api->buy($name, $quantity, $value);
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