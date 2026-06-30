<?php

use App\Http\Controllers\Api\AuthContoller;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthContoller::class, 'login']);

Route::group(['middleware' => ['auth:api']], function () {
    Route::get('user', [AuthContoller::class, 'user']);
});