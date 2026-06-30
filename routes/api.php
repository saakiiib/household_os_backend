<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MembersController;
use App\Http\Controllers\Api\TasksController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

// Legacy compat
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:api')->group(function () {
    Route::get('user', [AuthController::class, 'user']);
});

/*
|--------------------------------------------------------------------------
| Phase 2: Household Members & Roles (Protected Routes)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->group(function () {

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
});