<?php

namespace App\Providers;

use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService($app->make(\Kreait\Firebase\Contract\Messaging::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
