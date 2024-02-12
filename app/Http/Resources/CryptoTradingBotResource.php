<?php
/**
 * Created by PhpStorm.
 * User: Denis Kostaev
 * Date: 12/02/2024
 * Time: 12:33
 */

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class CryptoTradingBotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray(Request $request): array|JsonSerializable|Arrayable
    {
        return [
            'percentage_change' => $this->percentage_change,
            'price'             => $this->price,
            'ticker'            => $this->ticker,
            'created_at'        => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

}
