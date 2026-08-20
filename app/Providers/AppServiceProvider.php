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

        // Register custom lost connection detector to handle Hostinger's 'Operation not permitted' [2002] errors
        $this->app->bind(
            \Illuminate\Contracts\Database\LostConnectionDetector::class,
            \App\Database\CustomLostConnectionDetector::class
        );

        // Cache Passport access token validation queries to avoid hitting MySQL on every API request
        $this->app->bind(
            \League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface::class,
            \App\Passport\CachedAccessTokenRepository::class
        );

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
