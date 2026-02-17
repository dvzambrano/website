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

Route::prefix('telegrambot')->group(function () {
    Route::get('/', 'TelegramBotController@test');
});

Route::prefix('telegram')->group(function () {
    Route::post('/bot/{key}', 'TelegramBotController@handle')
        ->middleware('tenant.detector')
        ->name('telegram-bot-webhhok');

    // Ruta para el WebApp de escaneo
    // URL final ejemplo: https://tudominio.com/telegram/scanner
    Route::get('/scanner/{gpsrequired}/{botname}/{instance?}', 'TelegramBotController@initScanner')->name('telegram-scanner-init');
    Route::post('/scanner/store', 'TelegramBotController@storeScan')->name('telegram-scanner-store');

    // Ruta para autenticacion con Telegram
    Route::get('/auth/callback', 'TelegramController@loginCallback')
        ->name('telegram.callback');

});
