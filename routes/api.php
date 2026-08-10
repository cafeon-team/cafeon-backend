<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BenefitController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NoshowPolicyController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OwnerDashboardController;
use App\Http\Controllers\Api\OwnerReservationController;
use App\Http\Controllers\Api\PostLikeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StoreController;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// API 명세서 기준 인증 경로
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/signup', [AuthController::class, 'register']);

// 기존 프론트엔드 호환용 경로(추후 제거 가능)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/stores', [StoreController::class, 'index']);
Route::get('/stores/{store}', [StoreController::class, 'show']);
Route::get('/stores/{store}/menus', [MenuController::class, 'index']);
Route::get('/menus/{menu}', [MenuController::class, 'show']);
Route::get('/stores/{store}/reviews', [ReviewController::class, 'index']);
Route::get('/stores/{store}/congestion', [StoreController::class, 'availability']);
Route::get('/stores/{store}/availability', [StoreController::class, 'availability']);
Route::get('/stores/{store}/reservation-slots', [ReservationController::class, 'slots']);
Route::get('/stores/{store}/noshow-policy', [NoshowPolicyController::class, 'show']);

Route::get('/posts', function (Request $request) {
    return Post::query()
        ->with(['store:id,name,slug', 'category:id,name,slug', 'author:id,name'])
        ->withCount(['comments', 'likes'])
        ->where('status', 'PUBLISHED')
        ->when($request->filled('store_id'), fn ($query) => $query->where('store_id', $request->integer('store_id')))
        ->when($request->filled('category'), function ($query) use ($request) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', (string) $request->string('category')));
        })
        ->latest('published_at')
        ->paginate(10);
});

Route::get('/posts/{slug}', function (string $slug) {
    $post = Post::query()
        ->with([
            'store:id,name,slug',
            'category:id,name,slug',
            'author:id,name',
            'images',
            'tags:id,name,slug',
        ])
        ->withCount(['comments', 'likes'])
        ->where('slug', $slug)
        ->where('status', 'PUBLISHED')
        ->firstOrFail();

    $post->increment('view_count');

    return $post;
});

Route::get('/posts/{post}/comments', function (Post $post) {
    return $post->comments()
        ->with([
            'user:id,name',
            'replies' => fn ($query) => $query
                ->where('status', 'VISIBLE')
                ->with('user:id,name')
                ->oldest(),
        ])
        ->whereNull('parent_id')
        ->where('status', 'VISIBLE')
        ->oldest()
        ->get();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/users/me', [ProfileController::class, 'update']);
    Route::put('/users/me/password', [ProfileController::class, 'updatePassword']);
    Route::get('/users/me/coupons', [BenefitController::class, 'coupons']);
    Route::get('/users/me/membership', [BenefitController::class, 'membership']);
    Route::post('/stores/{store}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::get('/users/me/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/users/me/orders/{order}', [OrderController::class, 'show']);
    Route::post('/users/me/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::get('/users/me', [AuthController::class, 'me']);
    Route::get('/users/me/reservations', [ReservationController::class, 'mine']);
    Route::get('/users/me/reservations/{reservation}', [ReservationController::class, 'showMine']);
    Route::delete('/users/me/reservations/{reservation}', [ReservationController::class, 'cancelMine']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/posts/{post}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    Route::post('/posts/{post}/likes', [PostLikeController::class, 'store']);
    Route::delete('/posts/{post}/likes', [PostLikeController::class, 'destroy']);
    Route::get('/owner/stores/{store}/dashboard', [OwnerDashboardController::class, 'show']);
    Route::post('/reservations', [ReservationController::class, 'storeFromPayload']);
    Route::post('/stores/{store}/reservations', [ReservationController::class, 'store']);
    Route::get('/stores/{store}/reservations', [OwnerReservationController::class, 'index']);
    Route::patch('/reservations/{reservation}/status', [OwnerReservationController::class, 'updateStatus']);
    Route::put('/stores/{store}/noshow-policy', [NoshowPolicyController::class, 'update']);
});
