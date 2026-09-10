<?php

use App\Http\Controllers\Admin\LoginController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Database\Seeders\UserSeeder;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);

Route::match(['get', 'post'], '/logout', function () {
    auth()->logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

// Destructive reset is available only to authenticated test administrators.
// APP_ENV=local is deliberately insufficient: deployed logs currently use local.
Route::get('/clean-db', function () {
    abort_unless(app()->environment(['testing', 'local']), 403, 'Database reset is disabled outside testing.');

    DB::table('users')->orderBy('id')->chunkById(500, function ($users) {
        foreach ($users as $user) {
            foreach (['', 'auth:', 'api:'] as $prefix) {
                RateLimiter::clear($prefix.sha1((string) $user->id));
            }
        }
    });
    $publicKey = sha1(request()->route()->getDomain().'|'.request()->ip());
    RateLimiter::clear('auth:'.$publicKey);
    RateLimiter::clear($publicKey);

    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    try {
        $tables = [
            'activity_logs', 'jobs', 'failed_jobs', 'job_batches',
            'documents', 'document_files', 'households', 'household_members',
            'invitations', 'notifications', 'payments', 'renewals',
            'renewal_vehicle_services', 'sessions', 'subscriptions',
            'subscription_transactions', 'apple_notification_logs', 'tasks',
            'users', 'vehicles', 'device_tokens',
            'oauth_access_tokens', 'oauth_refresh_tokens',
            'oauth_auth_codes', 'oauth_device_codes',
        ];
        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    (new UserSeeder())->run();

    return new \Illuminate\Http\Response(
        '<h1>Database cleaned successfully.</h1><p>Sign in again on devices.</p>',
        200,
        ['Content-Type' => 'text/html']
    );
});

require __DIR__.'/admin.php';
