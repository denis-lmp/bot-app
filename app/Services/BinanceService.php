<?php

namespace App\Services;

use App\Jobs\SendTelegramNotification;
use App\Models\CryptoTrading;
use App\Models\CryptoTradingBot;
use App\Repositories\Contracts\BinanceServiceInterface;
use App\Repositories\CryptoTradingBotRepository;
use App\Repositories\CryptoTradingRepository;
use Binance\API;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BinanceService implements BinanceServiceInterface
{
    const STATUS_SUCCESS    = 'FILLED';
    const STATUS_PROCESSING = 'PROCESSING';

    const BUY_STATUS  = 'BUY';
    const SELL_STATUS = 'SELL';

    const MAX_COUNT = 5000;

    protected API $api;
    protected CryptoTradingRepository $cryptoTradingRepository;
    protected CryptoTradingBotRepository $cryptoTradingBotRepository;

    protected string $allowedCrypto = 'BTCUSDT';

    public function __construct(
        CryptoTradingRepository $cryptoTradingRepository,
        CryptoTradingBotRepository $cryptoTradingBotRepository
    ) {
        $this->cryptoTradingRepository    = $cryptoTradingRepository;
        $this->cryptoTradingBotRepository = $cryptoTradingBotRepository;

        $this->api = new API(config('binance.binance_api'), config('binance.binance_secret'));
    }

    /**
     * @throws Exception
     */
    public function getCheckTradingBids(): array
    {
        $trades = array();

        $trades['allTrades'] = $this->cryptoTradingRepository->getAllOrdered('created_at', 'DESC');

        $lastMadeTrade = $this->cryptoTradingRepository->getLastMadeTrade('created_at', 'DESC');

        $price = $this->getTickerPrice($this->allowedCrypto);

        if (!$price) {
            return $trades;
        }

        return $this->processTrades($trades, $lastMadeTrade, $price);
    }

    /**
     * @param $ticker
     * @return float|null
     */
    private function getTickerPrice($ticker): ?float
    {
        $prices = $this->getTickerPrices();

        return $prices[$ticker] ?? null;
    }

    /**
     * Retrieves ticker prices from the API.
     *
     * @return array An array containing the ticker prices.
     *               The array will be empty if the API call fails.
     */
    private function getTickerPrices(): array
    {
        try {
            return $this->api->prices();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * @param  array  $trades
     * @param $lastTradeMade
     * @param $currentPrice
     * @return array
     * @throws Exception
     */
    private function processTrades(array $trades, $lastTradeMade, $currentPrice): array
    {
        $percentChange = 0;

        if ($lastTradeMade) {
            $percentChange = $this->calculatePercentChange($lastTradeMade->price, $currentPrice);
            $this->processLastTrade($lastTradeMade, $this->allowedCrypto, $currentPrice, $percentChange);
        }

        $this->saveLatestTrade($this->allowedCrypto, $currentPrice, $percentChange);

        return $trades;
    }

    private function calculatePercentChange($oldPrice, $newPrice): float
    {
        if ($oldPrice == 0) {
            return 0;
        }
        $decreaseValue = $newPrice - $oldPrice;
        return round(($decreaseValue / $oldPrice) * 100, 2);
    }

    /**
     * @param $lastTradeMade
     * @param $ticker
     * @param $currentPrice
     * @param $percentChange
     * @return void
     * @throws Exception
     */
    private function processLastTrade($lastTradeMade, $ticker, $currentPrice, $percentChange): void
    {
        if ($lastTradeMade->buy_sell == self::BUY_STATUS) {
            if ($this->checkIfLastOrderProcessed($lastTradeMade, $percentChange)) {
                $this->processToSell($lastTradeMade, $ticker, $currentPrice);
            }
        } else {
            if ($this->checkIfLastOrderProcessed($lastTradeMade, $percentChange)) {
                $this->processToBuy($lastTradeMade, $ticker, $currentPrice);
            }
        }
    }

    /**
     * @param $lastTradeMade
     * @param $percentChangeTrade
     * @return bool
     * @throws Exception
     */
    private function checkIfLastOrderProcessed($lastTradeMade, $percentChangeTrade): bool
    {
        //Check if the status is filled of the last job
        if ($lastTradeMade->status == self::STATUS_SUCCESS) {
            return true;
        }

        try {
            $orderStatus = $this->api->orderStatus($lastTradeMade->ticker, $lastTradeMade->order_id);
        } catch (Exception $e) {
            $orderStatus = [];
        }

        //If filled then we can carry on
        if ($orderStatus['status'] == self::STATUS_SUCCESS) {
            //If filled mark as filled and save
            $lastTradeMade->status = self::STATUS_SUCCESS;
            $lastTradeMade->save();

            return true;
        } else {
            //If checks over 500 then delete the old order and carry on
            if (((Carbon::now()->subMinutes(10)->toDateTimeString() > $lastTradeMade->created_at) &&
                 ($percentChangeTrade < -5.00)) ||
                ($percentChangeTrade > 5.00) ||
                $lastTradeMade->checks > 10000)
            {
                $response = $this->api->cancel($lastTradeMade->ticker, $lastTradeMade->order_id);
                $lastTradeMade->delete();

                return true;
            }

            $lastTradeMade->status = self::STATUS_PROCESSING;
            $lastTradeMade->save();

            //If not add one more check for filled
            $lastTradeMade->checks = (int)$lastTradeMade->checks + 1;
            $lastTradeMade->save();
        }

        //Return false as we still have an order
        return false;
    }

    private function processToSell($lastTradeMade, string $name, float $currentPrice): void
    {
        if ($lastTradeMade->ticker == $name && $lastTradeMade->buy_sell == self::BUY_STATUS) {
            DB::transaction(function () use ($lastTradeMade, $name, $currentPrice) {

                $cryptoTrading = $this->cryptoTradingRepository->getModel();

                $decreaseValue = $currentPrice - (float)$lastTradeMade->price;
                $percentChange = round(($decreaseValue / $currentPrice) * 100, 2);

                if ($percentChange >= 1.00) {
                    $balance = $this->getSellBalance();

                    $quantity = $this->calculateQuantity($balance);

                    $orderId = null;
                    if ($quantity) {
                        $orderId = $this->placeSellOrder($lastTradeMade->ticker, $quantity, $currentPrice,
                            $percentChange);
                    }

                    if (isset($orderId)) {
                        $this->saveTrading($cryptoTrading, $name, $currentPrice, $lastTradeMade->price, $quantity,
                            $orderId, (int)$lastTradeMade->checks + 1, self::SELL_STATUS);

                        $this->deleteRaws($name);

                        return true;
                    }
                }
            });
        }
    }

    /**
     * Check for profile balance
     */
    public function getSellBalance(string $name = 'BTC'): float|string
    {
        try {
            $balances = $this->api->balances($name);
        } catch (Exception $e) {
            $balances = [];
        }

        if (isset($balances[$name])) {
            $quantity = $balances[$name]['available'] ?? null;
            return round((float)$quantity, 8);
        }

        return 'Balance checking error';
    }

    /**
     * @param  float  $quantity
     * @return bool|string
     */
    private function calculateQuantity(float $quantity): bool|string
    {
        // Define a small value that we consider "close to zero"
        $smallValue = 1E-4;

        // If calculated quantity is effectively zero, do something (like throw an exception, return a specific value, etc.)
        if ($quantity < $smallValue) {
            $this->sendNotification('Error while calculating quantity');
            return false;
        }

        return bcdiv($quantity, 1, 4) ?? false;
    }

    private function sendNotification(
        string $message,
        string $percentChange = '',
        string $quantity = '',
        $orderId = null
    ): void {
        SendTelegramNotification::dispatch($message.' Change: '.$percentChange.' '.$quantity.' orderId = '.$orderId);
    }

    /**
     * @param  string  $name
     * @param  string  $quantity
     * @param  float  $value
     * @param $percentChange
     * @return mixed|string
     */
    private function placeSellOrder(string $name, string $quantity, float $value, $percentChange): mixed
    {
        $order = $this->api->sell($name, $quantity, $value);
        $this->sendNotification('SELLING - % changed for ', $percentChange, $quantity, $order['orderId'] ?? '');

        return $order['orderId'] ?? '';
    }

    /**
     * @param  CryptoTrading  $cryptoTrading
     * @param $ticker
     * @param $price
     * @param $oldPrice
     * @param $quantity
     * @param $orderId
     * @param $checks
     * @param $status
     * @return void
     */
    private function saveTrading(
        Model $cryptoTrading,
        $ticker,
        $price,
        $oldPrice,
        $quantity,
        $orderId,
        $checks,
        $status
    ): void {
        $cryptoTrading->ticker    = $ticker;
        $cryptoTrading->price     = $price;
        $cryptoTrading->old_price = $oldPrice;
        $cryptoTrading->buy_sell  = $status;
        $cryptoTrading->amount    = $quantity;
        $cryptoTrading->order_id  = $orderId;
        $cryptoTrading->checks    = (int)$checks;

        $cryptoTrading->save();
    }

    private function processToBuy($lastTradeMade, string $name, float $currentPrice): void
    {
        if ($lastTradeMade->buy_sell == self::SELL_STATUS) {
            DB::transaction(function () use ($lastTradeMade, $name, $currentPrice) {
                $usdtBalance = $this->getCoinBalance();
                $cryptoTrading = $this->cryptoTradingRepository->getModel();

                $lastTradeMadeLive = $this->cryptoTradingRepository->getLastMadeTrade('created_at', 'DESC');

                if ($lastTradeMadeLive->buy_sell == self::SELL_STATUS) {
                    $percentChange = $this->calculatePercentChange($lastTradeMadeLive->price, $currentPrice);

                    if ($percentChange < -1.00) {
                        $quantity = $this->calculateQuantity($usdtBalance / $currentPrice);

                        if ($quantity) {
                            $orderId = $this->placeBuyOrder($lastTradeMade->ticker, $quantity, $currentPrice,
                                $percentChange);

                            if (isset($orderId)) {
                                $this->saveTrading($cryptoTrading, $name, $currentPrice, $lastTradeMadeLive->price,
                                    $quantity, $orderId, (int)$lastTradeMade->checks + 1, self::BUY_STATUS);

                                return true;
                            }
                        }
                    }
                }
            });
        }
    }

    private function getUSDTBalance()
    {
        $balances = $this->api->balances('USDT');
        $balance  = $balances['USDT']['available'] ?? null;

        return round((float)$balance, 3);
    }

    /**
     * @param  string  $name
     * @param  string  $quantity
     * @param  float  $currentPrice
     * @param $percentChange
     * @return mixed|string
     */
    private function placeBuyOrder(string $name, string $quantity, float $currentPrice, $percentChange): mixed
    {
        $order = $this->api->buy($name, $quantity, $currentPrice);
        $this->sendNotification('BUYING - % changed for ', $percentChange, $quantity, $order['orderId'] ?? '');

        return $order['orderId'] ?? '';
    }

    private function saveLatestTrade($ticker, $price, $percentChange): void
    {
        $cryptoTradingBot                    = new CryptoTradingBot();
        $cryptoTradingBot->ticker            = $ticker;
        $cryptoTradingBot->price             = $price;
        $cryptoTradingBot->percentage_change = $percentChange;
        $cryptoTradingBot->save();
    }

    /**
     * Check for price
     */
    public function getPrice(string $name = 'BTCUSDT'): string
    {
        try {
            $prices = $this->api->price($name);
        } catch (Exception $e) {
            $prices = '';
        }

        return $prices;
    }


    public function deleteRaws(string $name): void
    {
        $allRaws = $this->cryptoTradingBotRepository->bindSearchCriteria(['ticker' => $name]);
        $allRawsCount = $allRaws->count();

        if ($allRawsCount >= self::MAX_COUNT) {
            $allRaws->take(3000)->delete();
        }
    }

    /**
     * Test sell
     */
    public function testSell(string $ticker = 'BTCUSDT', int $value = 0)
    {
        $quantity = 0.00047;

        $tickers = $this->api->prices();

        $currentPrice = $tickers[$this->allowedCrypto[0]] ?? null;
        $quantity     = sprintf("%.6f", $quantity);

        return $this->api->sell($ticker, $quantity, $currentPrice);
    }

    /**
     *  Test buy
     */
    public function testBuy(string $ticker = 'BTCUSDT', int $value = 0)
    {
        $q        = $this->calculateAvailableBuyQuantity();
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

        $tickers      = $this->api->prices();
        $currentPrice = $tickers[$this->allowedCrypto[0]] ?? null;
        $quantity     = round((float)$usdtBalance / (float)$currentPrice, 5);

        return [$quantity ?? null, $currentPrice];
    }

    public function exportOrdersToDatabase()
    {
        $orders = $this->getOrders();
        $orders = json_decode($orders);
        foreach ($orders as $key => $order) {
            $keyPast       = $key - 1 ?? 0;
            $ct            = new CryptoTrading();
            $ct->order_id  = $order->orderId;
            $ct->ticker    = $order->symbol ?? '';
            $ct->amount    = $order->executedQty;
            $ct->price     = $order->price;
            $ct->old_price = $orders[$keyPast]->price ?? '';
            $ct->buy_sell  = $order->side;
            $ct->order_id  = $order->orderId;
            $ct->status    = $order->status;
            $ct->checks    = 1;
            $ct->save();
        }

        return json_encode('Success.');
    }

    /**
     * @param  string  $name
     * @return bool|string
     * @throws Exception
     */
    public function getOrders(string $name = 'BTCUSDT'): bool|string
    {
        return json_encode($this->api->orders($name));
    }

    /**
     * @param  string  $name
     * @return array
     * @throws Exception
     */
    public function getBalance(string $name = 'BTC'): array
    {
        try {
            return $this->api->balances($name);
        } catch (Exception $e) {
            return [];
        }
    }
}