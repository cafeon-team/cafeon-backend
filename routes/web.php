<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Test\MvcVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/swagger', '/api/documentation')->name('swagger');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminController::class, 'login'])->name('admin.login');
    Route::post('/admin/login', [AdminController::class, 'authenticate'])->name('admin.authenticate');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'super_admin'])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::patch('/users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('users.toggle');
    Route::patch('/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('users.role');
    Route::get('/stores', [AdminController::class, 'stores'])->name('stores');
    Route::patch('/stores/{store}/toggle', [AdminController::class, 'toggleStore'])->name('stores.toggle');
    Route::get('/commerce', [AdminController::class, 'commerce'])->name('commerce');
    Route::get('/moderation', [AdminController::class, 'moderation'])->name('moderation');
    Route::patch('/reviews/{review}', [AdminController::class, 'updateReview'])->name('reviews.update');
    Route::patch('/inquiries/{inquiry}', [AdminController::class, 'answerInquiry'])->name('inquiries.answer');
    Route::get('/system', [AdminController::class, 'system'])->name('system');
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
