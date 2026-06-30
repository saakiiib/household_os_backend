<?php

use App\Http\Controllers\FrontendController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require __DIR__ . '/admin.php';

Route::get('/clear', function () {
    Auth::logout();
    session()->flush();
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('view:clear');
    return "Cleared!";
});

Route::fallback(function () {
    return redirect('/');
});

Auth::routes();

Route::get('/', [FrontendController::class, 'index'])->name('frontend.home');

Route::get('/about', [FrontendController::class, 'about'])->name('frontend.about');

Route::get('/shop', [FrontendController::class, 'shop'])->name('frontend.shop');

Route::get('/contact', [FrontendController::class, 'contact'])->name('frontend.contact');

Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');

Route::group(['prefix' => 'user/', 'middleware' => ['auth', 'is_user']], function () {
    Route::get('/dashboard', [HomeController::class, 'userHome'])->name('user.dashboard');
});