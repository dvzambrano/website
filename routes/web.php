<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\TwoFactorAuthController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('templates.Gp.layout');
})->name('home');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified', '2fa'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/2fa/enable', [TwoFactorAuthController::class, 'showEnableForm'])->name('2fa.enable');
    Route::post('/2fa/enable', [TwoFactorAuthController::class, 'enable']);
    Route::get('/2fa/disable', [TwoFactorAuthController::class, 'showDisableForm'])->name('2fa.disable');
    Route::post('/2fa/disable', [TwoFactorAuthController::class, 'disable']);
    Route::get('/2fa/verify', [TwoFactorAuthController::class, 'showVerifyForm'])->name('2fa.verify');
    Route::post('/2fa/verify', [TwoFactorAuthController::class, 'verify'])->name('2fa.verify.post');
});

Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

require __DIR__ . '/auth.php';

Route::get('/cache', function () {
    echo 'Intentando borrar cache<br/>';
    $commands = [
        'route:cache',
        'cache:clear',
        'config:clear',
        'config:cache',
        'view:clear',
        'optimize',
        'route:clear',
    ];
    foreach ($commands as $line) {
        $code = \Artisan::call($line);
        echo $line . ': ' . $code . '<br/>';
    }
    die('Hecho!');
});

Route::get('/test/{name?}', [TestController::class, 'test'])->name('test-byname');

// /logs/* ahora las sirve dvzambrano/filesystem directamente (Routes/web.php
// del paquete, vía su propio RouteServiceProvider) — nada que registrar aquí.

Route::get('/report/{format}/{name}', [FileController::class, 'renderAndDestroy'])->name('report-byname');
