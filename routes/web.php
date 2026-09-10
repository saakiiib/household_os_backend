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
Route::match(['get', 'post'], '/clean-db', function () {
    abort_unless(app()->environment(['testing', 'local']), 403, 'Database reset is disabled outside testing.');

    // GET: show confirmation form
    if (request()->isMethod('get')) {
        $csrf = csrf_field();
        return response()->headers->set('Content-Type', 'text/html')->setContent(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clean Database – HouseholdOS</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
  .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 32px; max-width: 440px; width: 100%; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  h1 { font-size: 20px; color: #111827; margin: 0 0 8px; }
  p { font-size: 14px; color: #6b7280; margin: 0 0 20px; line-height: 1.5; }
  ul { font-size: 13px; color: #6b7280; margin: 0 0 20px; padding-left: 18px; line-height: 1.6; }
  button { background: #ef4444; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
  button:hover { background: #dc2626; }
</style>
</head>
<body>
<div class="card">
  <h1>Clean Database</h1>
  <p>This will <strong>permanently delete</strong> all test data and re-seed the default user.</p>
  <ul>
    <li>Users, households, subscriptions</li>
    <li>Payments, transactions, notifications</li>
    <li>OAuth tokens, sessions, device tokens</li>
    <li>Rate-limit counters</li>
  </ul>
  <form method="POST">
    {$csrf}
    <button type="submit" onclick="return confirm('Are you sure? This cannot be undone.')">Reset Everything</button>
  </form>
</div>
</body>
</html>
HTML
        );
    }

    // Clear only this app's known per-user throttle keys, including legacy
    // unprefixed keys. Never flush a possibly shared Redis/cache store.
    DB::table('users')->orderBy('id')->chunkById(500, function ($users) {
        foreach ($users as $user) {
            foreach (['', 'auth:', 'api:'] as $prefix) {
                RateLimiter::clear($prefix.sha1((string) $user->id));
            }
        }
    });
    // Public auth is keyed by domain + IP rather than user ID.
    $publicKey = sha1(request()->route()->getDomain().'|'.request()->ip());
    RateLimiter::clear('auth:'.$publicKey);
    RateLimiter::clear($publicKey);

    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
    try {
        $tables = [
            'activity_logs',
            'jobs',
            'failed_jobs',
            'job_batches',
            'documents',
            'document_files',
            'households',
            'household_members',
            'invitations',
            'notifications',
            'payments',
            'renewals',
            'renewal_vehicle_services',
            'sessions',
            'subscriptions',
            'subscription_transactions',
            'apple_notification_logs',
            'tasks',
            'users',
            'vehicles',
            'device_tokens',
            'oauth_access_tokens',
            'oauth_refresh_tokens',
            'oauth_auth_codes',
            'oauth_device_codes',
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

    return response()->json([
        'success' => true,
        'message' => 'Test tables and Apple notification records reset; known user rate-limit counters cleared. Sign in again on devices. Apple sandbox purchase history must be reset separately in Apple settings.',
    ]);
})->middleware(['auth', 'admin']);

require __DIR__.'/admin.php';
