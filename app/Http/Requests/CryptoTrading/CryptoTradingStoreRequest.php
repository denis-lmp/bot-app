<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 11/12/2023
 * Time: 15:40
 */

namespace App\Http\Requests\CryptoTrading;

class CryptoTradingStoreRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ticker'    => 'required',
            'amount'    => 'required',
            'price'     => 'required',
            'old_price' => 'required',
            'buy_sell'  => 'required',
            'order_id'  => 'required',
            'status'    => 'required',
        ];
    }
}

