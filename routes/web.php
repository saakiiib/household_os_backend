<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/clear', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('view:clear');
    return "Cleared!";
});

Route::get('/clean-db', function () {
    DB::statement('SET FOREIGN_KEY_CHECKS=0');

    $tables = [
        'activity_logs',
        'document_files',
        'documents',
        'renewal_documents',
        'renewals',
        'vehicles',
        'tasks',
        'invitations',
        'household_members',
        'households',
        'notifications',
        'payments',
        'subscriptions',
        'personal_access_tokens',
        'users',
    ];

    foreach ($tables as $table) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            DB::table($table)->truncate();
        }
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=1');

    return response()->json([
        'success' => true,
        'message' => 'Database cleaned successfully.',
    ]);
});

Route::fallback(function () {
    return response()->json(['message' => 'Not found'], 404);
});
