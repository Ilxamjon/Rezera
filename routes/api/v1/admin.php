<?php

use App\Http\Controllers\Api\V1\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\BusinessController;
use App\Http\Controllers\Api\V1\Admin\BusinessVerificationController as AdminBusinessVerificationController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\NotificationDeliveryController;
use App\Http\Controllers\Api\V1\Admin\PaymentController;
use App\Http\Controllers\Api\V1\Admin\PricingRuleController;
use App\Http\Controllers\Api\V1\Admin\PromoCodeController;
use App\Http\Controllers\Api\V1\Admin\ReservationController;
use App\Http\Controllers\Api\V1\Admin\ReferralController;
use App\Http\Controllers\Api\V1\Admin\ReviewController;
use App\Http\Controllers\Api\V1\Admin\SearchController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionPlanController as AdminSubscriptionPlanController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'platform.admin', 'throttle:admin'])
    ->prefix('admin')
    ->name('api.v1.admin.')
    ->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard.show');
        Route::get('/analytics/overview', [AdminAnalyticsController::class, 'overview'])->name('analytics.overview');
        Route::get('/search', [SearchController::class, 'index'])->name('search.index');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.update-status');

        Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/{business}', [BusinessController::class, 'show'])->name('businesses.show');
        Route::patch('/businesses/{business}/status', [BusinessController::class, 'updateStatus'])->name('businesses.update-status');
        Route::patch('/businesses/{business}/verification', [BusinessController::class, 'updateVerification'])->name('businesses.update-verification');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
        Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.update-status');

        Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

        Route::get('/notification-deliveries', [NotificationDeliveryController::class, 'index'])->name('notification-deliveries.index');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::patch('/settings/{key}', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/{review}', [ReviewController::class, 'show'])->name('reviews.show');
        Route::patch('/reviews/{review}/status', [ReviewController::class, 'updateStatus'])->name('reviews.update-status');
        Route::patch('/reviews/{review}/hide', [ReviewController::class, 'hide'])->name('reviews.hide');
        Route::patch('/reviews/{review}/restore', [ReviewController::class, 'restore'])->name('reviews.restore');

        Route::get('/promo-codes', [PromoCodeController::class, 'index'])->name('promo-codes.index');
        Route::post('/promo-codes', [PromoCodeController::class, 'store'])->name('promo-codes.store');
        Route::get('/promo-codes/{promo}', [PromoCodeController::class, 'show'])->name('promo-codes.show');
        Route::patch('/promo-codes/{promo}', [PromoCodeController::class, 'update'])->name('promo-codes.update');
        Route::delete('/promo-codes/{promo}', [PromoCodeController::class, 'destroy'])->name('promo-codes.destroy');

        Route::get('/pricing-rules', [PricingRuleController::class, 'index'])->name('pricing-rules.index');
        Route::get('/pricing-rules/{rule}', [PricingRuleController::class, 'show'])->name('pricing-rules.show');

        Route::get('/referrals', [ReferralController::class, 'index'])->name('referrals.index');
        Route::get('/referrals/{referral}', [ReferralController::class, 'show'])->name('referrals.show');

        Route::get('/subscription-plans', [AdminSubscriptionPlanController::class, 'index'])->name('subscription-plans.index');
        Route::post('/subscription-plans', [AdminSubscriptionPlanController::class, 'store'])->name('subscription-plans.store');
        Route::get('/subscription-plans/{plan}', [AdminSubscriptionPlanController::class, 'show'])->name('subscription-plans.show');
        Route::patch('/subscription-plans/{plan}', [AdminSubscriptionPlanController::class, 'update'])->name('subscription-plans.update');
        Route::get('/subscription-plans/{plan}/entitlements', [AdminSubscriptionPlanController::class, 'entitlements'])->name('subscription-plans.entitlements');
        Route::put('/subscription-plans/{plan}/entitlements', [AdminSubscriptionPlanController::class, 'syncEntitlements'])->name('subscription-plans.entitlements.sync');

        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('/subscriptions/{subscription}', [AdminSubscriptionController::class, 'show'])->name('subscriptions.show');

        Route::get('/business-verifications', [AdminBusinessVerificationController::class, 'index'])->name('business-verifications.index');
        Route::get('/business-verifications/{verification}', [AdminBusinessVerificationController::class, 'show'])->name('business-verifications.show');
        Route::post('/business-verifications/{verification}/approve', [AdminBusinessVerificationController::class, 'approve'])->name('business-verifications.approve');
        Route::post('/business-verifications/{verification}/reject', [AdminBusinessVerificationController::class, 'reject'])->name('business-verifications.reject');
    });
