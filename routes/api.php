<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MembersController;
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
    });
});