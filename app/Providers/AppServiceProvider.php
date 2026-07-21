<?php

namespace App\Providers;

use App\Listeners\ForwardTronDealerServiceWebhook;
use Dvzambrano\TronDealer\Events\TronDealerDepositConfirmed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TronDealerDepositConfirmed::class, ForwardTronDealerServiceWebhook::class);
    }
}
