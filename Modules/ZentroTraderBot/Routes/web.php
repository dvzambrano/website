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


Route::get('/', 'LandingController@index')->name('zentrotraderbot.landing');
Route::get('/dashboard', 'LandingController@dashboard')
    ->middleware(['web', 'telegrambot.auth'])
    ->name('zentrotraderbot.dashboard');
Route::prefix('pay')->group(function () {
    Route::get('/chains', 'LandingController@getChains')
        ->name('pay.api.chains');
    Route::get('/balances/{address?}/{chainId?}/{networkKey?}', 'LandingController@getBalances')
        ->name('pay.api.balances');

    // PASO 2: Obtener la cotización (Cuanto llega a Kashio)
    Route::get('/quote', 'LandingController@getQuote')
        ->name('pay.api.quote');

    // PASO 3: Crear la orden final (Obtener el objeto de transacción para firmar)
    Route::post('/order', 'LandingController@createOrder')
        ->name('pay.api.order');

    // Vista principal del asistente (Donde el usuario aterriza desde Telegram)
    Route::get('/{user}', 'LandingController@pay')
        ->name('zentrotraderbot.pay');
});

Route::prefix('zentrotraderbot')->group(function () {
    //Route::get('/', 'LandingController@index')->name('zentrotraderbot.landing');
    //Route::get('/dashboard', 'LandingController@dashboard')->name('zentrotraderbot.dashboard');
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
    Route::get('{action}/{key}/{secret}/{user_id}', 'RampController@redirect')->middleware('tenant')->name('ramp-redirect');
    Route::get('/success/{key}/{secret}/{user_id}', 'RampController@success')->middleware('tenant')->name('ramp-success');
});

Route::prefix('webhook')->group(function () {
    Route::post('/ramp', 'RampController@processWebhook')->name('ramp-webhook');
    Route::post('/trondealer', 'RampController@processWebhook')->name('trondealer-webhook');
});
