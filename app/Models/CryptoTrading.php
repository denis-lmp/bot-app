<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CryptoTrading extends Model
{
    use HasFactory;

    protected $table = 'crypto_tradings';

    protected $fillable = [
        'order_id'
    ];

}
