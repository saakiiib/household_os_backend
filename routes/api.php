<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\MembersController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\RenewalsController;
use App\Http\Controllers\Api\TasksController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
// Auth routes: strict rate limit (10 req/min) to prevent brute force
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Legacy compat (also throttled)
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::middleware('auth:api')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
});

/*
|--------------------------------------------------------------------------
| Phase 2: Household Members & Roles (Protected Routes)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {

    // Invitation acceptance (any authenticated user, no household membership required yet)
    Route::post('invitations/{token}/accept', [MembersController::class, 'acceptInvitation']);

    // Household member routes (require active membership)
    Route::prefix('households/{household_id}')->group(function () {

        // Any active member can list members
        Route::middleware('household.role')->group(function () {
            Route::get('members', [MembersController::class, 'index']);
        });

        // Admin or co-admin can invite members
        Route::middleware('household.role:admin,co-admin')->group(function () {
            Route::post('invitations', [MembersController::class, 'invite']);
        });

        // Admin only can manage roles and remove members
        Route::middleware('household.role:admin')->group(function () {
            Route::patch('members/{member_id}', [MembersController::class, 'updateRole']);
            Route::delete('members/{member_id}', [MembersController::class, 'removeMember']);
        });

        /*
        |----------------------------------------------------------------------
        | Phase 3: Task Routes
        |----------------------------------------------------------------------
        | GET    households/{household_id}/tasks           → index  (any member)
        | POST   households/{household_id}/tasks           → store  (any member)
        */
        Route::middleware('household.role')->group(function () {
            Route::get('tasks', [TasksController::class, 'index']);
            Route::post('tasks', [TasksController::class, 'store']);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Phase 3: Task Detail Routes (no household_id in path)
    |--------------------------------------------------------------------------
    | GET    /api/tasks/{task_id}         → show
    | PATCH  /api/tasks/{task_id}         → update
    | DELETE /api/tasks/{task_id}         → destroy
    | POST   /api/tasks/{task_id}/complete → complete
    */
    Route::prefix('tasks')->group(function () {
        Route::get('{task_id}', [TasksController::class, 'show']);
        Route::patch('{task_id}', [TasksController::class, 'update']);
        Route::delete('{task_id}', [TasksController::class, 'destroy']);
        Route::post('{task_id}/complete', [TasksController::class, 'complete']);
    });

    /*
    |--------------------------------------------------------------------------
    | Phase 4: Renewal Routes
    |--------------------------------------------------------------------------
    | Household-scoped:
    |   GET    /api/households/{household_id}/renewals           → index
    |   POST   /api/households/{household_id}/renewals           → store
    |   GET    /api/households/{household_id}/renewals/upcoming  → upcoming
    | Detail routes:
    |   GET    /api/renewals/{renewal_id}          → show
    |   PATCH  /api/renewals/{renewal_id}          → update
    |   DELETE /api/renewals/{renewal_id}          → destroy
    |   POST   /api/renewals/{renewal_id}/complete → complete
    */
    Route::prefix('households/{household_id}')
        ->middleware('household.role')
        ->group(function () {
            Route::get('renewals/upcoming', [RenewalsController::class, 'upcoming'])->middleware('throttle:renewals');
            Route::get('renewals', [RenewalsController::class, 'index'])->middleware('throttle:renewals');
            Route::post('renewals', [RenewalsController::class, 'store'])->middleware('throttle:renewals');

            // Phase 5: Documents
            Route::get('documents', [DocumentsController::class, 'index']);
            Route::post('documents', [DocumentsController::class, 'store'])->middleware('throttle:uploads');
        });

    Route::prefix('renewals')->middleware('throttle:renewals')->group(function () {
        Route::get('{renewal_id}', [RenewalsController::class, 'show']);
        Route::patch('{renewal_id}', [RenewalsController::class, 'update']);
        Route::delete('{renewal_id}', [RenewalsController::class, 'destroy']);
        Route::post('{renewal_id}/complete', [RenewalsController::class, 'complete']);
    });

    /*
    |--------------------------------------------------------------------------
    | Phase 5: Document Detail Routes
    |--------------------------------------------------------------------------
    | GET    /api/documents/{document_id}/download → download
    | DELETE /api/documents/{document_id}          → destroy
    */
    Route::prefix('documents')->group(function () {
        Route::get('{document_id}/download', [DocumentsController::class, 'download'])->middleware('throttle:downloads');
        Route::delete('{document_id}', [DocumentsController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Phase 6: Notification Routes
    |--------------------------------------------------------------------------
    | GET    /api/notifications                  → index
    | PUT    /api/notifications/{id}/read        → read
    | POST   /api/notifications/read-all         → readAll
    | POST   /api/notifications/fcm-token        → updateFcmToken
    */
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationsController::class, 'index']);
        Route::put('{notification_id}/read', [NotificationsController::class, 'read']);
        Route::post('read-all', [NotificationsController::class, 'readAll']);
        Route::post('fcm-token', [NotificationsController::class, 'updateFcmToken']);
    });
});