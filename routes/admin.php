<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\HouseholdController;
use App\Http\Controllers\Admin\TaskController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\RenewalController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('admin.users.show');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('admin.users.toggleStatus');

    Route::get('/households', [HouseholdController::class, 'index'])->name('admin.households.index');
    Route::get('/households/{household}', [HouseholdController::class, 'show'])->name('admin.households.show');

    Route::get('/tasks', [TaskController::class, 'index'])->name('admin.tasks.index');

    Route::get('/documents', [DocumentController::class, 'index'])->name('admin.documents.index');

    Route::get('/renewals', [RenewalController::class, 'index'])->name('admin.renewals.index');

    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('admin.subscriptions.index');

    Route::get('/payments', [PaymentController::class, 'index'])->name('admin.payments.index');
});
