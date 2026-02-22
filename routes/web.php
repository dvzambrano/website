<?php

use Illuminate\Support\Facades\Route;

use Modules\ZentroTraderBot\Http\Controllers\LandingController;

Route::get('/', [LandingController::class, 'index'])->name('home');

