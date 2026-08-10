<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withEvents(discover: false)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    
        $middleware->alias([
            //'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            //'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

            // Tu middleware
            '2fa' => \App\Http\Middleware\Verify2FA::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Illuminate\Console\Scheduling\Schedule $schedule) {
        // Procesar jobs de la cola cada minuto.
        // --max-time=59 acota el worker a la propia ventana del minuto (sin esto,
        // un lote grande —p.ej. un anuncio a muchos suscriptores— puede dejar el
        // proceso corriendo más de lo esperado) y runInBackground() evita que
        // schedule:run se quede esperándolo. withoutOverlapping(5) reemplaza el
        // candado por defecto de 24h: si una corrida llega a quedar colgada, el
        // procesamiento de la cola se autorecupera en minutos en vez de bloquearse
        // el resto del día.
        $schedule->command('queue:work --stop-when-empty --max-time=59')
            ->everyMinute()
            ->withoutOverlapping(5)
            ->runInBackground();
    })
    ->create();
