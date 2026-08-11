<?php

use App\Http\Controllers\Test\MvcVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

if (app()->environment('local')) {
    Route::prefix('test/mvc')->name('test.mvc.')->controller(MvcVerificationController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/stores', 'stores')->name('stores');
        Route::get('/menus', 'menus')->name('menus');
        Route::get('/users', 'users')->name('users');
        Route::get('/reservations', 'reservations')->name('reservations');
        Route::get('/orders', 'orders')->name('orders');
        Route::get('/benefits', 'benefits')->name('benefits');
        Route::get('/reviews', 'reviews')->name('reviews');
        Route::get('/dashboard', 'dashboard')->name('dashboard');
        Route::get('/blog-api', 'blogApi')->name('blogApi');
    });
}
