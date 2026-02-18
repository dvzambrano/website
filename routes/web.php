<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TestController;
use Modules\ZentroTraderBot\Http\Controllers\LandingController;

Route::get('/', [LandingController::class, 'index'])->name('home');

// Ruta para probar funciones de TelegramController
Route::get('/test', [TestController::class, 'test']);
