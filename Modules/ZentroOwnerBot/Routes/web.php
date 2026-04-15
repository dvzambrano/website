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

Route::prefix('ZentroOwnerBot')->group(function () {
    Route::get('/', 'ZentroOwnerBotController@index');
});

Route::prefix('webhook')->group(function () {
    Route::post('/generator', 'ServicesController@processWebhook')->name('generator-webhook');
});
