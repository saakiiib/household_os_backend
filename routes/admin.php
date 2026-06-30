<?php

use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompanyDetailsController;
use App\Http\Controllers\Admin\PageSeoController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'admin/', 'middleware' => ['auth', 'is_admin']], function () {

    // Dashboard
    Route::get('/dashboard', [HomeController::class, 'adminHome'])->name('admin.dashboard');

    Route::get('/profile', [AdminProfileController::class, 'index'])->name('admin.profile');
    Route::post('/profile', [AdminProfileController::class, 'update'])->name('admin.profile.update');

    // Category crud
    Route::get('/category', [CategoryController::class, 'index'])->name('category.index');
    Route::get('/parent-categories', [CategoryController::class, 'parentCategories'])->name('parent.categories');
    Route::post('/category', [CategoryController::class, 'store'])->name('category.store');
    Route::get('/category/{id}/edit', [CategoryController::class, 'edit'])->name('category.edit');
    Route::post('/category-update', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/category/{id}', [CategoryController::class, 'delete'])->name('category.delete');
    Route::post('/category-status', [CategoryController::class, 'toggleStatus'])->name('category.toggleStatus');

    // Company Details
    Route::get('/company-details', [CompanyDetailsController::class, 'index'])->name('companyDetails');
    Route::post('/company-details', [CompanyDetailsController::class, 'update'])->name('companyDetails.update');

    // Page SEO
    Route::get('/page-seo', [PageSeoController::class, 'index'])->name('page-seo.index');
    Route::post('/page-seo/update', [PageSeoController::class, 'update'])->name('page-seo.update');
    Route::get('/page-seo/{id}/edit', [PageSeoController::class, 'edit'])->name('page-seo.edit');
});