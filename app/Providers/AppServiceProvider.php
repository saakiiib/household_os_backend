<?php

namespace App\Providers;

use App\Services\NotificationService;
use App\Support\CachedAccessTokenRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });

        $this->app->extend(AccessTokenRepositoryInterface::class, function ($repository) {
            return new CachedAccessTokenRepository($repository, Cache::store('file'));
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
