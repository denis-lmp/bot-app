<?php

use App\Http\Controllers\CryptoTradingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('/tools', '\App\Http\Controllers\ToolsController@getCheckTradingBids')->name('tools');
    Route::get('/balance/{coin}', '\App\Http\Controllers\ToolsController@getBalance')->name('balance');
    Route::get('/price', '\App\Http\Controllers\ToolsController@getPrice')->name('price');


    Route::get('/testSell', '\App\Http\Controllers\ToolsController@testSell')->name('testSell');
    Route::get('/testBuy', '\App\Http\Controllers\ToolsController@testBuy')->name('testBuy');

    Route::get('/orders', '\App\Http\Controllers\ToolsController@getOrders')->name('orders');
    Route::get('/export-orders', '\App\Http\Controllers\ToolsController@exportOrders')->name('export-orders');

    Route::resource('tradings', CryptoTradingController::class);

    Route::get('/profile', function () {
        //
    })->withoutMiddleware([EnsureTokenIsValid::class]);

});

require __DIR__.'/auth.php';
