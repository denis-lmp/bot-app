<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 11/01/2024
 * Time: 11:56
 */

namespace App\Classes\Builders;

use App\Models\CryptoTrading;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

class StringTableBuilder
{
    /**
     * @param  Collection  $data
     * @param  string  $price
     * @return string
     */
    public static function makeStringTable(Collection $data, string $price): string
    {
        $data = $data->map(function (CryptoTrading $cryptoTrading) {
            return [
                $cryptoTrading->ticker == 'BTCUSDT' ? 'BTC' : $cryptoTrading->ticker,
                $cryptoTrading->buy_sell,
                $cryptoTrading->amount,
//                number_format($cryptoTrading->old_price, 2),
                number_format($cryptoTrading->price, 2),
            ];
        });

        // Create an instance of BufferedOutput to capture the console output
        $output = new BufferedOutput();

        // Create an instance of Table
        $table = new Table($output);

        // Set the table headers
        $table->setHeaders(['Name', 'Action', 'Amount', 'Price']);

        // Add rows to the table
        foreach ($data as $row) {
            $table->addRow($row);
        }

        // Render the table
        $table->render();

        // Fetch the table content from the output buffer as a string
        // Return the table as a string
        $asString = $output->fetch();

        return $asString . PHP_EOL . 'Current price: ' . $price;
    }

}