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


    // Esta ruta siempre debe estar antes de '/auth/{key}'
    Route::get('/auth/logout', function () {
        // 1. Cierra la sesión del Guard de Laravel (si existiera)
        Auth::logout();
        // 2. Invalida la sesión del usuario en el servidor (borra TODO lo guardado)
        request()->session()->invalidate();
        // 3. Regenera el token CSRF para evitar ataques de fijación de sesión
        request()->session()->regenerateToken();
        return redirect('/');
    })->name('telegram.logout');
    // Ruta para autenticacion con Telegram
    Route::get('/auth/{key}', 'TelegramController@loginCallback')
        ->middleware('telegrambot.detector')
        ->name('telegram.callback');

});
