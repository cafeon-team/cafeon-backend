<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BenefitController;
use App\Http\Controllers\Api\BlogTaxonomyController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\Api\FrontendFeatureController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\NoshowPolicyController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OwnerDashboardController;
use App\Http\Controllers\Api\OwnerReservationController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\PostLikeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StoreController;
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
Route::get('/faqs', [FrontendFeatureController::class, 'faqs']);
Route::get('/recommendations/stores', [FrontendFeatureController::class, 'recommendations']);
Route::get('/stores/{store}/post-categories', [BlogTaxonomyController::class, 'categories']);
Route::get('/stores/{store}/tags', [BlogTaxonomyController::class, 'tags']);

Route::get('/posts', [PostApiController::class, 'index']);
Route::get('/posts/{slug}', [PostApiController::class, 'show']);
Route::get('/posts/{post}/comments', [CommentController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/uploads/images', [ImageUploadController::class, 'store']);
    Route::get('/users/me/favorites', [FrontendFeatureController::class, 'favorites']);
    Route::post('/stores/{store}/favorite', [FrontendFeatureController::class, 'favorite']);
    Route::delete('/stores/{store}/favorite', [FrontendFeatureController::class, 'unfavorite']);
    Route::get('/users/me/preferences', [FrontendFeatureController::class, 'preferences']);
    Route::put('/users/me/preferences', [FrontendFeatureController::class, 'updatePreferences']);
    Route::get('/users/me/inquiries', [FrontendFeatureController::class, 'inquiries']);
    Route::post('/users/me/inquiries', [FrontendFeatureController::class, 'storeInquiry']);
    Route::get('/users/me/inquiries/{inquiry}', [FrontendFeatureController::class, 'showInquiry']);
    Route::get('/users/me/membership-summary', [FrontendFeatureController::class, 'membershipSummary']);
    Route::get('/users/me/referral-code', [FrontendFeatureController::class, 'referralCode']);
    Route::post('/users/me/referrals/claim', [FrontendFeatureController::class, 'claimReferral']);
    Route::post('/admin/faqs', [FrontendFeatureController::class, 'storeFaq']);
    Route::put('/admin/faqs/{faq}', [FrontendFeatureController::class, 'updateFaq']);
    Route::delete('/admin/faqs/{faq}', [FrontendFeatureController::class, 'deleteFaq']);
    Route::patch('/admin/inquiries/{inquiry}/answer', [FrontendFeatureController::class, 'answerInquiry']);
    Route::post('/posts', [PostApiController::class, 'store']);
    Route::put('/posts/{post}', [PostApiController::class, 'update']);
    Route::delete('/posts/{post}', [PostApiController::class, 'destroy']);
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
    Route::patch('/comments/{comment}/status', [CommentController::class, 'updateStatus']);

    Route::post('/stores/{store}/post-categories', [BlogTaxonomyController::class, 'storeCategory']);
    Route::put('/post-categories/{category}', [BlogTaxonomyController::class, 'updateCategory']);
    Route::delete('/post-categories/{category}', [BlogTaxonomyController::class, 'destroyCategory']);
    Route::post('/stores/{store}/tags', [BlogTaxonomyController::class, 'storeTag']);
    Route::put('/tags/{tag}', [BlogTaxonomyController::class, 'updateTag']);
    Route::delete('/tags/{tag}', [BlogTaxonomyController::class, 'destroyTag']);

    Route::post('/posts/{post}/likes', [PostLikeController::class, 'store']);
    Route::delete('/posts/{post}/likes', [PostLikeController::class, 'destroy']);
    Route::get('/owner/stores/{store}/dashboard', [OwnerDashboardController::class, 'show']);
    Route::post('/reservations', [ReservationController::class, 'storeFromPayload']);
    Route::post('/stores/{store}/reservations', [ReservationController::class, 'store']);
    Route::get('/stores/{store}/reservations', [OwnerReservationController::class, 'index']);
    Route::patch('/reservations/{reservation}/status', [OwnerReservationController::class, 'updateStatus']);
    Route::put('/stores/{store}/noshow-policy', [NoshowPolicyController::class, 'update']);
});
