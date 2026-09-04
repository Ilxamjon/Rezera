<?php

use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BusinessCategoryController;
use App\Http\Controllers\Api\V1\BusinessController;
use App\Http\Controllers\Api\V1\BusinessReviewController;
use App\Http\Controllers\Api\V1\LoyaltyRewardController;
use App\Http\Controllers\Api\V1\PricingPreviewController;
use App\Http\Controllers\Api\V1\PromoCodeValidationController;
use App\Http\Controllers\Api\V1\PublicResourceController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\ReservationRulesController;
use Illuminate\Support\Facades\Route;

Route::get('/business-categories', [BusinessCategoryController::class, 'index'])
    ->name('api.v1.business-categories.index');

Route::middleware('optional.sanctum')->group(function (): void {
    Route::get('/businesses', [BusinessController::class, 'index'])
        ->name('api.v1.businesses.index');

    Route::get('/businesses/{business}', [BusinessController::class, 'show'])
        ->name('api.v1.businesses.show');
});

Route::get('/businesses/{business}/reservation-rules', [ReservationRulesController::class, 'show'])
    ->name('api.v1.businesses.reservation-rules.show');

Route::get('/businesses/{business}/resources', [PublicResourceController::class, 'index'])
    ->name('api.v1.businesses.resources.index');

Route::get('/businesses/{business}/availability', [AvailabilityController::class, 'index'])
    ->name('api.v1.businesses.availability.index');

Route::get('/businesses/{business}/resources/{resource}/availability', [AvailabilityController::class, 'show'])
    ->name('api.v1.businesses.resources.availability.show');

Route::get('/businesses/{business}/reviews', [BusinessReviewController::class, 'index'])
    ->name('api.v1.businesses.reviews.index');

Route::middleware(['auth:sanctum', 'throttle:booking'])->group(function (): void {
    Route::post('/businesses', [BusinessController::class, 'store'])
        ->name('api.v1.businesses.store');

    Route::post('/businesses/{business}/promo-codes/validate', [PromoCodeValidationController::class, 'store'])
        ->name('api.v1.businesses.promo-codes.validate');

    Route::post('/businesses/{business}/pricing/preview', [PricingPreviewController::class, 'store'])
        ->name('api.v1.businesses.pricing.preview');

    Route::post('/businesses/{business}/reservations', [ReservationController::class, 'store'])
        ->name('api.v1.businesses.reservations.store');

    Route::post('/businesses/{business}/reviews', [BusinessReviewController::class, 'store'])
        ->name('api.v1.businesses.reviews.store');

    Route::get('/businesses/{business}/rewards', [LoyaltyRewardController::class, 'index'])
        ->name('api.v1.businesses.rewards.index');

    Route::post('/businesses/{business}/rewards/{reward}/redeem', [LoyaltyRewardController::class, 'redeem'])
        ->name('api.v1.businesses.rewards.redeem');
});
