<?php

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

Route::prefix('zentrotraderbot')->group(function () {
    Route::get('/', 'ZentroTraderBotController@index');
});

Route::prefix('tradingview')->group(function () {
    //https://micalme.com/tradingview/community
    //https://micalme.com/tradingview/client/816767995
    Route::post('/{alert?}/{user?}', 'TradingViewController@webhook')->name('tradingview-webhook');
    Route::get('/{alert?}/{user?}', function () {
        echo 'This is your client URL';
    });
});

Route::prefix('ramp')->group(function () {
    Route::get('/{key}/{secret}/{user_id}', 'RampController@redirect')->middleware('tenant')->name('ramp-redirect');
    Route::get('/success', 'RampController@success')->name('ramp-success');
    Route::post('/order', 'RampController@webhookOrder')->name('ramp-webhook-order');
    Route::post('/kyc', 'RampController@webhookKyc')->name('ramp-webhook-kyc');
});
