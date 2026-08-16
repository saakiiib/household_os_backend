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

        // Keep Blade's compiled view cache out of the project directory.
        $compiledPath = sys_get_temp_dir() . '/hos-blade-cache';
        if (! is_dir($compiledPath)) {
            @mkdir($compiledPath, 0755, true);
        }
        config(['view.compiled' => $compiledPath]);
    }

    public function boot(): void
    {
        //
    }
}
