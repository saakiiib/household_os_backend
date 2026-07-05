<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\MembersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('verify-email', [AuthController::class, 'verify']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);

    Route::middleware('auth:api')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Authenticated)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {

    // Household CRUD
    Route::get('households', [HouseholdController::class, 'index']);
    Route::post('households', [HouseholdController::class, 'store']);
    Route::post('households/join', [HouseholdController::class, 'joinByCode'])->withoutMiddleware(['throttle:60,1']);
    Route::get('households/{id}', [HouseholdController::class, 'show']);
    Route::patch('households/{id}', [HouseholdController::class, 'update']);
    Route::delete('households/{id}', [HouseholdController::class, 'destroy']);
    Route::post('households/{id}/regenerate-invite', [HouseholdController::class, 'regenerateInvite']);

    // Invitation acceptance
    Route::post('invitations/{token}/accept', [MembersController::class, 'acceptInvitation']);

    // Household member routes
    Route::prefix('households/{household_id}')->group(function () {

        Route::middleware('household.role')->group(function () {
            Route::get('members', [MembersController::class, 'index']);
        });

        Route::middleware('household.role:admin,co-admin')->group(function () {
            Route::post('invitations', [MembersController::class, 'invite']);
        });

        Route::middleware('household.role:admin')->group(function () {
            Route::patch('members/{member_id}', [MembersController::class, 'updateRole']);
            Route::delete('members/{member_id}', [MembersController::class, 'removeMember']);
        });
    });
});
