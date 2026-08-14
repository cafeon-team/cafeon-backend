<?php

use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Test\MvcVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/social/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['google', 'kakao', 'naver'])
    ->name('social.redirect');
Route::get('/auth/social/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['google', 'kakao', 'naver'])
    ->name('social.callback');

if (app()->environment(['local', 'testing'])) {
    Route::view('/test/social-login', 'test.social-login')->name('test.social-login');
    Route::view('/test/social-login/callback', 'test.social-login-callback')->name('test.social-login.callback');
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
