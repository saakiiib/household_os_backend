<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PagesController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\HouseholdController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\RenewalController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\IapController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    // Admin dashboard and UI pages
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('/p/{page}', [PagesController::class, 'show'])->name('admin.page');
    Route::get('/p/{page}/{id}', [PagesController::class, 'detail'])->name('admin.page.detail');

    // Household Management
    Route::get('/households', [HouseholdController::class, 'index'])->name('admin.households.index');
    Route::get('/households/{household}', [HouseholdController::class, 'show'])->name('admin.households.show');

    // User Management
    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggleStatus');

    // Admin Users (admin role)
    Route::get('/admins', [AdminController::class, 'index'])->name('admin.admins.index');
    Route::post('/admins', [AdminController::class, 'store'])->name('admin.admins.store');
    Route::delete('/admins/{user}', [AdminController::class, 'destroy'])->name('admin.admins.destroy');

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index'])->name('admin.tasks.index');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('admin.tasks.show');

    // Documents
    Route::get('/documents', [DocumentController::class, 'index'])->name('admin.documents.index');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('admin.documents.show');

    // Renewals
    Route::get('/renewals', [RenewalController::class, 'index'])->name('admin.renewals.index');
    Route::get('/renewals/{renewal}', [RenewalController::class, 'show'])->name('admin.renewals.show');

    // Subscriptions
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('admin.subscriptions.index');
    Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('admin.subscriptions.show');

    // Payments
    Route::get('/payments', [PaymentController::class, 'index'])->name('admin.payments.index');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('admin.payments.show');

    // IAP Management (Apple & Google in-app purchase verification and re-verification)
    Route::get('/iap', [IapController::class, 'index'])->name('admin.iap.index');
    Route::get('/iap/apple/{transaction}', [IapController::class, 'appleShow'])->name('admin.iap.apple.show');
    Route::get('/iap/google/{transaction}', [IapController::class, 'googleShow'])->name('admin.iap.google.show');
    Route::post('/iap/apple/{transaction}/reverify', [IapController::class, 'reverifyApple'])->name('admin.iap.apple.reverify');
    Route::post('/iap/google/{transaction}/reverify', [IapController::class, 'reverifyGoogle'])->name('admin.iap.google.reverify');
});
