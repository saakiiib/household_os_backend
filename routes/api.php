<?php

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\MembersController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TasksController;
use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\RenewalsController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\VehiclesController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('clean-db', function () {
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

Route::get('config', [ConfigController::class, 'index']);
Route::get('subscription/plans', [SubscriptionController::class, 'index']);

// PayPal return URL (user redirected here after approving payment)
Route::get('subscription/paypal-capture', function () {
    return response()->json([
        'message' => 'Payment approved. You can close this window and return to the app.',
    ]);
});

// Payment webhooks (public, no auth)
Route::post('subscription/stripe/webhook', [PaymentController::class, 'stripeWebhook']);
Route::post('subscription/paypal/webhook', [PaymentController::class, 'paypalWebhook']);

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('social/google', [SocialAuthController::class, 'google']);
    Route::post('social/apple', [SocialAuthController::class, 'apple']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('verify-email', [AuthController::class, 'verify']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);

    // Public invite code endpoints (no auth required)
    Route::post('invite/preview', [AuthController::class, 'previewInviteCode']);
    Route::post('invite/join', [AuthController::class, 'joinByInviteCode']);

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
    Route::get('invitations/pending', [AuthController::class, 'pendingInvitations']);

    // Profile
    Route::match(['put', 'post'], 'profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'changePassword']);
    Route::delete('profile', [ProfileController::class, 'destroy']);

    // Household CRUD
    Route::get('households', [HouseholdController::class, 'index']);
    Route::post('households', [HouseholdController::class, 'store']);
    Route::post('households/join', [HouseholdController::class, 'joinByCode'])->withoutMiddleware(['throttle:60,1']);
    Route::get('households/{id}', [HouseholdController::class, 'show']);
    Route::patch('households/{id}', [HouseholdController::class, 'update']);
    Route::delete('households/{id}', [HouseholdController::class, 'destroy']);
    Route::post('households/{id}/regenerate-invite', [HouseholdController::class, 'regenerateInvite']);
    Route::post('households/{id}/abandon', [HouseholdController::class, 'abandonHousehold']);
    Route::post('households/leave', [HouseholdController::class, 'leave']);

    // Invitation acceptance
    Route::post('invitations/{token}/accept', [MembersController::class, 'acceptInvitation']);

    // Household member routes
    Route::prefix('households/{household_id}')->group(function () {

        Route::middleware('household.role')->group(function () {
            Route::get('members', [MembersController::class, 'index']);
        });

        Route::middleware('household.role')->group(function () {
            Route::post('invitations', [MembersController::class, 'invite']);
            Route::delete('invitations/{invitation_id}', [MembersController::class, 'cancelInvitation']);
        });

        Route::middleware('household.role:admin')->group(function () {
            Route::patch('members/{member_id}', [MembersController::class, 'updateRole']);
            Route::delete('members/{member_id}', [MembersController::class, 'removeMember']);
            Route::patch('members/{member_id}/approve', [MembersController::class, 'approveMember']);
            Route::patch('members/{member_id}/reject', [MembersController::class, 'rejectMember']);
        });

        // Activity Log
        Route::get('activities', [ActivityController::class, 'index']);

        // Tasks
        Route::get('tasks', [TasksController::class, 'index']);
        Route::post('tasks', [TasksController::class, 'store']);
        Route::get('tasks/{task_id}', [TasksController::class, 'show']);
        Route::patch('tasks/{task_id}', [TasksController::class, 'update']);
        Route::delete('tasks/{task_id}', [TasksController::class, 'destroy']);
        Route::patch('tasks/{task_id}/complete', [TasksController::class, 'complete']);
        Route::patch('tasks/{task_id}/start', [TasksController::class, 'startInProgress']);

        // Documents
        Route::get('documents', [DocumentsController::class, 'index']);
        Route::post('documents', [DocumentsController::class, 'store']);
        Route::get('documents/{document_id}', [DocumentsController::class, 'show']);
        Route::patch('documents/{document_id}', [DocumentsController::class, 'update']);
        Route::delete('documents/{document_id}', [DocumentsController::class, 'destroy']);

        // Document files
        Route::post('documents/{document_id}/files', [DocumentsController::class, 'uploadFiles']);
        Route::delete('documents/{document_id}/files/{file_id}', [DocumentsController::class, 'deleteFile']);
        Route::get('documents/{document_id}/files/{file_id}/download', [DocumentsController::class, 'downloadFile']);

        // Renewals
        Route::get('renewals', [RenewalsController::class, 'index']);
        Route::post('renewals', [RenewalsController::class, 'store']);
        Route::get('renewals/{renewal_id}', [RenewalsController::class, 'show']);
        Route::patch('renewals/{renewal_id}', [RenewalsController::class, 'update']);
        Route::delete('renewals/{renewal_id}', [RenewalsController::class, 'destroy']);
        Route::patch('renewals/{renewal_id}/complete', [RenewalsController::class, 'complete']);
        Route::post('renewals/{renewal_id}/renew', [RenewalsController::class, 'renew']);
        Route::get('renewals/{renewal_id}/download', [RenewalsController::class, 'download']);

        // Vehicles
        Route::get('vehicles', [VehiclesController::class, 'index']);
        Route::post('vehicles', [VehiclesController::class, 'store']);
        Route::get('vehicles/{vehicle_id}', [VehiclesController::class, 'show']);
        Route::patch('vehicles/{vehicle_id}', [VehiclesController::class, 'update']);
        Route::delete('vehicles/{vehicle_id}', [VehiclesController::class, 'destroy']);
    });

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('fcm-token', [NotificationController::class, 'saveFcmToken']);
    Route::delete('fcm-token', [NotificationController::class, 'deleteFcmToken']);

    // Subscription & Payments
    Route::get('subscription/current', [SubscriptionController::class, 'current']);
    Route::post('subscription/checkout', [PaymentController::class, 'checkout']);
    Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::get('subscription/history', [SubscriptionController::class, 'history']);
    Route::post('subscription/paypal/capture', [PaymentController::class, 'paypalCapture']);
    Route::post('subscription/stripe/confirm', [PaymentController::class, 'stripeConfirm']);
});

// Stripe return URLs (public, matched by WebView)
Route::get('subscription/stripe/success', function (\Illuminate\Http\Request $request) {
    $sessionId = $request->query('session_id');
    return response()->json([
        'message' => 'Payment successful. You can close this window.',
        'session_id' => $sessionId,
    ]);
});

Route::get('subscription/stripe/cancel', function () {
    return response()->json([
        'message' => 'Payment cancelled.',
    ]);
});
