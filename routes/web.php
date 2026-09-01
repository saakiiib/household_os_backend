<?php

use App\Http\Controllers\Admin\LoginController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
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

Route::get('/clean-db', function () {
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');

    DB::table('households')->update(['created_by_user_id' => 0]);
    DB::table('invitations')->update(['invited_by_user_id' => 0, 'accepted_by_user_id' => 0]);
    DB::table('tasks')->update(['created_by_user_id' => 0, 'assigned_user_id' => 0, 'parent_task_id' => 0]);
    DB::table('documents')->update(['created_by_user_id' => 0]);
    DB::table('vehicles')->update(['created_by_user_id' => 0]);
    DB::table('renewals')->update(['created_by_user_id' => 0, 'vehicle_id' => 0]);

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
        'tasks',
        'users',
        'vehicles',
    ];

    foreach ($tables as $table) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            DB::table($table)->truncate();
        }
    }

    DB::statement('SET FOREIGN_KEY_CHECKS = 1');

    UserSeeder::seedReviewer();

    return response()->json([
        'success' => true,
        'message' => 'Database cleaned successfully.',
    ]);
});

require __DIR__.'/admin.php';
