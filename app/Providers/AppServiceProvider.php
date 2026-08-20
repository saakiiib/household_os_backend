<?php

namespace App\Providers;

use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the Firebase Messaging client with a hard HTTP timeout. A slow or
        // unreachable FCM endpoint would otherwise block the cron request for a
        // long time, holding its MySQL connection open and (together with
        // overlapping cron ticks) exhausting MySQL connections — which is what
        // produces the "Operation not permitted" [2002] freeze.
        $this->app->singleton(\Kreait\Firebase\Contract\Messaging::class, function ($app) {
            $credentialsPath = $app->basePath(env('GOOGLE_APPLICATION_CREDENTIALS'));

            $httpOptions = \Kreait\Firebase\Http\HttpClientOptions::default()
                ->withTimeout(10)
                ->withConnectTimeout(5);

            $factory = new \Kreait\Firebase\Factory();
            if ($credentialsPath && is_file($credentialsPath)) {
                $factory = $factory->withServiceAccount($credentialsPath);
            }

            return $factory
                ->withHttpClientOptions($httpOptions)
                ->createMessaging();
        });

        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService($app->make(\Kreait\Firebase\Contract\Messaging::class));
        });

        // Cache Passport access token validation queries to avoid hitting MySQL
        // on every API request. IMPORTANT: this only helps when CACHE_STORE is
        // NOT "database" (a DB-backed cache just adds more MySQL queries). With
        // CACHE_STORE=file it offloads token checks from MySQL.
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
