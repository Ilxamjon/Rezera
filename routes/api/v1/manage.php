<?php

use App\Http\Controllers\Api\V1\Manage\AnalyticsController as ManageAnalyticsController;
use App\Http\Controllers\Api\V1\Manage\AvailabilityController as ManageAvailabilityController;
use App\Http\Controllers\Api\V1\Manage\BusinessCalendarController;
use App\Http\Controllers\Api\V1\Manage\BusinessDashboardController;
use App\Http\Controllers\Api\V1\Manage\BusinessManagementController;
use App\Http\Controllers\Api\V1\Manage\BusinessMemberController;
use App\Http\Controllers\Api\V1\Manage\BusinessMemberInvitationController;
use App\Http\Controllers\Api\V1\Manage\BusinessOnboardingController;
use App\Http\Controllers\Api\V1\Manage\BusinessReadinessController;
use App\Http\Controllers\Api\V1\Manage\BusinessStaffController;
use App\Http\Controllers\Api\V1\Manage\BusinessSubscriptionController;
use App\Http\Controllers\Api\V1\Manage\BusinessVerificationController;
use App\Http\Controllers\Api\V1\Manage\BusinessWorkingHoursController;
use App\Http\Controllers\Api\V1\Manage\LoyaltyProgramController as ManageLoyaltyProgramController;
use App\Http\Controllers\Api\V1\Manage\LoyaltyRewardController as ManageLoyaltyRewardController;
use App\Http\Controllers\Api\V1\Manage\PaymentController as ManagePaymentController;
use App\Http\Controllers\Api\V1\Manage\PricingRuleController as ManagePricingRuleController;
use App\Http\Controllers\Api\V1\Manage\PromoCodeController as ManagePromoCodeController;
use App\Http\Controllers\Api\V1\Manage\ReservationCheckInController as ManageReservationCheckInController;
use App\Http\Controllers\Api\V1\Manage\ReservationController as ManageReservationController;
use App\Http\Controllers\Api\V1\Manage\ReservationSettingsController;
use App\Http\Controllers\Api\V1\Manage\ResourceCategoryController;
use App\Http\Controllers\Api\V1\Manage\ResourceController;
use App\Http\Controllers\Api\V1\Manage\ResourceQrController;
use App\Http\Controllers\Api\V1\Manage\ReviewController as ManageReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('manage')
    ->name('api.v1.manage.')
    ->group(function (): void {
        Route::get('/businesses', [BusinessManagementController::class, 'index'])
            ->name('businesses.index');

        Route::get('/businesses/{business}', [BusinessManagementController::class, 'show'])
            ->name('businesses.show');

        Route::patch('/businesses/{business}', [BusinessManagementController::class, 'update'])
            ->name('businesses.update');

        Route::get('/businesses/{business}/members', [BusinessMemberController::class, 'index'])
            ->name('businesses.members.index');

        Route::get('/businesses/{business}/members/{member}', [BusinessMemberController::class, 'show'])
            ->name('businesses.members.show');

        Route::post('/businesses/{business}/members', [BusinessMemberController::class, 'store'])
            ->name('businesses.members.store');

        Route::patch('/businesses/{business}/members/{member}', [BusinessMemberController::class, 'update'])
            ->name('businesses.members.update');

        Route::delete('/businesses/{business}/members/{member}', [BusinessMemberController::class, 'destroy'])
            ->name('businesses.members.destroy');

        Route::get('/businesses/{business}/staff/roles', [BusinessStaffController::class, 'roles'])
            ->name('businesses.staff.roles');

        Route::get('/businesses/{business}/staff/me', [BusinessStaffController::class, 'me'])
            ->name('businesses.staff.me');

        Route::get('/businesses/{business}/invitations', [BusinessMemberInvitationController::class, 'index'])
            ->name('businesses.invitations.index');

        Route::post('/businesses/{business}/invitations', [BusinessMemberInvitationController::class, 'store'])
            ->name('businesses.invitations.store');

        Route::delete('/businesses/{business}/invitations/{invitation}', [BusinessMemberInvitationController::class, 'destroy'])
            ->name('businesses.invitations.destroy');

        Route::get('/businesses/{business}/working-hours', [BusinessWorkingHoursController::class, 'show'])
            ->name('businesses.working-hours.show');

        Route::put('/businesses/{business}/working-hours', [BusinessWorkingHoursController::class, 'update'])
            ->name('businesses.working-hours.update');

        Route::get('/businesses/{business}/reservation-settings', [ReservationSettingsController::class, 'show'])
            ->name('businesses.reservation-settings.show');

        Route::put('/businesses/{business}/reservation-settings', [ReservationSettingsController::class, 'update'])
            ->name('businesses.reservation-settings.update');

        Route::post('/businesses/{business}/reservation-settings/reset', [ReservationSettingsController::class, 'reset'])
            ->name('businesses.reservation-settings.reset');

        Route::get('/businesses/{business}/resource-categories', [ResourceCategoryController::class, 'index'])
            ->name('businesses.resource-categories.index');

        Route::post('/businesses/{business}/resource-categories', [ResourceCategoryController::class, 'store'])
            ->name('businesses.resource-categories.store');

        Route::get('/businesses/{business}/resource-categories/{category}', [ResourceCategoryController::class, 'show'])
            ->name('businesses.resource-categories.show');

        Route::patch('/businesses/{business}/resource-categories/{category}', [ResourceCategoryController::class, 'update'])
            ->name('businesses.resource-categories.update');

        Route::delete('/businesses/{business}/resource-categories/{category}', [ResourceCategoryController::class, 'destroy'])
            ->name('businesses.resource-categories.destroy');

        Route::get('/businesses/{business}/resources', [ResourceController::class, 'index'])
            ->name('businesses.resources.index');

        Route::post('/businesses/{business}/resources', [ResourceController::class, 'store'])
            ->name('businesses.resources.store');

        Route::get('/businesses/{business}/resources/occupancy', [ManageReservationCheckInController::class, 'resourceOccupancy'])
            ->name('businesses.resources.occupancy');

        Route::get('/businesses/{business}/resources/{resource}', [ResourceController::class, 'show'])
            ->name('businesses.resources.show');

        Route::patch('/businesses/{business}/resources/{resource}', [ResourceController::class, 'update'])
            ->name('businesses.resources.update');

        Route::delete('/businesses/{business}/resources/{resource}', [ResourceController::class, 'destroy'])
            ->name('businesses.resources.destroy');

        Route::get('/businesses/{business}/availability', [ManageAvailabilityController::class, 'index'])
            ->name('businesses.availability.index');

        Route::get('/businesses/{business}/dashboard', [BusinessDashboardController::class, 'show'])
            ->name('businesses.dashboard.show');

        Route::get('/businesses/{business}/calendar/day', [BusinessCalendarController::class, 'day'])
            ->name('businesses.calendar.day');
        Route::get('/businesses/{business}/calendar/week', [BusinessCalendarController::class, 'week'])
            ->name('businesses.calendar.week');
        Route::get('/businesses/{business}/calendar/timeline', [BusinessCalendarController::class, 'timeline'])
            ->name('businesses.calendar.timeline');
        Route::get('/businesses/{business}/calendar/resources/{resource}', [BusinessCalendarController::class, 'resourceSchedule'])
            ->name('businesses.calendar.resources.show');

        Route::get('/businesses/{business}/subscription', [BusinessSubscriptionController::class, 'show'])
            ->name('businesses.subscription.show');
        Route::post('/businesses/{business}/subscription', [BusinessSubscriptionController::class, 'store'])
            ->name('businesses.subscription.store');
        Route::get('/businesses/{business}/subscription/usage', [BusinessSubscriptionController::class, 'usage'])
            ->name('businesses.subscription.usage');

        Route::get('/businesses/{business}/onboarding', [BusinessOnboardingController::class, 'show'])
            ->name('businesses.onboarding.show');
        Route::get('/businesses/{business}/readiness', [BusinessReadinessController::class, 'show'])
            ->name('businesses.readiness.show');
        Route::get('/businesses/{business}/verification', [BusinessVerificationController::class, 'show'])
            ->name('businesses.verification.show');
        Route::post('/businesses/{business}/verification', [BusinessVerificationController::class, 'store'])
            ->name('businesses.verification.store');
        Route::post('/businesses/{business}/verification/resubmit', [BusinessVerificationController::class, 'resubmit'])
            ->name('businesses.verification.resubmit');

        Route::post('/businesses/{business}/subscription/cancel', [BusinessSubscriptionController::class, 'cancel'])
            ->name('businesses.subscription.cancel');
        Route::post('/businesses/{business}/subscription/resume', [BusinessSubscriptionController::class, 'resume'])
            ->name('businesses.subscription.resume');

        Route::get('/businesses/{business}/reservations/today', [ManageReservationController::class, 'today'])
            ->name('businesses.reservations.today');

        Route::get('/businesses/{business}/payments', [ManagePaymentController::class, 'index'])
            ->name('businesses.payments.index');

        Route::get('/businesses/{business}/payments/{payment}', [ManagePaymentController::class, 'show'])
            ->name('businesses.payments.show');

        Route::get('/businesses/{business}/reservations/upcoming', [ManageReservationController::class, 'upcoming'])
            ->name('businesses.reservations.upcoming');

        Route::get('/businesses/{business}/reservations', [ManageReservationController::class, 'index'])
            ->name('businesses.reservations.index');

        Route::get('/businesses/{business}/reservations/{reservation}', [ManageReservationController::class, 'show'])
            ->name('businesses.reservations.show');

        Route::patch('/businesses/{business}/reservations/{reservation}', [ManageReservationController::class, 'updateStatus'])
            ->name('businesses.reservations.update-status');

        Route::post('/businesses/{business}/reservations/{reservation}/cancel', [ManageReservationController::class, 'cancel'])
            ->name('businesses.reservations.cancel');

        Route::post('/businesses/{business}/reservations/{reservation}/check-in', [ManageReservationCheckInController::class, 'checkIn'])
            ->name('businesses.reservations.check-in');

        Route::post('/businesses/{business}/reservations/{reservation}/check-in/qr', [ManageReservationCheckInController::class, 'checkInQr'])
            ->middleware('throttle:booking')
            ->name('businesses.reservations.check-in.qr');

        Route::post('/businesses/{business}/reservations/{reservation}/check-out', [ManageReservationCheckInController::class, 'checkOut'])
            ->name('businesses.reservations.check-out');

        Route::get('/businesses/{business}/operations/today', [ManageReservationCheckInController::class, 'todayOperations'])
            ->name('businesses.operations.today');

        Route::get('/businesses/{business}/sessions/active', [ManageReservationCheckInController::class, 'activeSessions'])
            ->name('businesses.sessions.active');

        Route::get('/businesses/{business}/resources/{resource}/qr', [ResourceQrController::class, 'show'])
            ->name('businesses.resources.qr.show');

        Route::post('/businesses/{business}/resources/{resource}/qr', [ResourceQrController::class, 'store'])
            ->name('businesses.resources.qr.store');

        Route::post('/businesses/{business}/resources/{resource}/qr/revoke', [ResourceQrController::class, 'revoke'])
            ->name('businesses.resources.qr.revoke');

        Route::get('/businesses/{business}/reviews', [ManageReviewController::class, 'index'])
            ->name('businesses.reviews.index');

        Route::get('/businesses/{business}/reviews/{review}', [ManageReviewController::class, 'show'])
            ->name('businesses.reviews.show');

        Route::post('/businesses/{business}/reviews/{review}/response', [ManageReviewController::class, 'storeResponse'])
            ->name('businesses.reviews.response.store');

        Route::patch('/businesses/{business}/reviews/{review}/response', [ManageReviewController::class, 'updateResponse'])
            ->name('businesses.reviews.response.update');

        Route::delete('/businesses/{business}/reviews/{review}/response', [ManageReviewController::class, 'destroyResponse'])
            ->name('businesses.reviews.response.destroy');

        Route::get('/businesses/{business}/promo-codes', [ManagePromoCodeController::class, 'index'])
            ->name('businesses.promo-codes.index');

        Route::post('/businesses/{business}/promo-codes', [ManagePromoCodeController::class, 'store'])
            ->name('businesses.promo-codes.store');

        Route::get('/businesses/{business}/promo-codes/{promo}', [ManagePromoCodeController::class, 'show'])
            ->name('businesses.promo-codes.show');

        Route::patch('/businesses/{business}/promo-codes/{promo}', [ManagePromoCodeController::class, 'update'])
            ->name('businesses.promo-codes.update');

        Route::delete('/businesses/{business}/promo-codes/{promo}', [ManagePromoCodeController::class, 'destroy'])
            ->name('businesses.promo-codes.destroy');

        Route::get('/businesses/{business}/pricing-rules', [ManagePricingRuleController::class, 'index'])
            ->name('businesses.pricing-rules.index');

        Route::post('/businesses/{business}/pricing-rules', [ManagePricingRuleController::class, 'store'])
            ->name('businesses.pricing-rules.store');

        Route::get('/businesses/{business}/pricing-rules/{rule}', [ManagePricingRuleController::class, 'show'])
            ->name('businesses.pricing-rules.show');

        Route::patch('/businesses/{business}/pricing-rules/{rule}', [ManagePricingRuleController::class, 'update'])
            ->name('businesses.pricing-rules.update');

        Route::delete('/businesses/{business}/pricing-rules/{rule}', [ManagePricingRuleController::class, 'destroy'])
            ->name('businesses.pricing-rules.destroy');

        Route::post('/businesses/{business}/pricing/preview', [ManagePricingRuleController::class, 'preview'])
            ->name('businesses.pricing.preview');

        Route::get('/businesses/{business}/loyalty', [ManageLoyaltyProgramController::class, 'show'])
            ->name('businesses.loyalty.show');

        Route::put('/businesses/{business}/loyalty', [ManageLoyaltyProgramController::class, 'update'])
            ->name('businesses.loyalty.update');

        Route::get('/businesses/{business}/customers/{user}/loyalty', [ManageLoyaltyProgramController::class, 'customerLoyalty'])
            ->name('businesses.customers.loyalty.show');

        Route::post('/businesses/{business}/customers/{user}/loyalty/adjust', [ManageLoyaltyProgramController::class, 'adjust'])
            ->name('businesses.customers.loyalty.adjust');

        Route::get('/businesses/{business}/rewards', [ManageLoyaltyRewardController::class, 'index'])
            ->name('businesses.rewards.index');

        Route::post('/businesses/{business}/rewards', [ManageLoyaltyRewardController::class, 'store'])
            ->name('businesses.rewards.store');

        Route::get('/businesses/{business}/rewards/{reward}', [ManageLoyaltyRewardController::class, 'show'])
            ->name('businesses.rewards.show');

        Route::patch('/businesses/{business}/rewards/{reward}', [ManageLoyaltyRewardController::class, 'update'])
            ->name('businesses.rewards.update');

        Route::delete('/businesses/{business}/rewards/{reward}', [ManageLoyaltyRewardController::class, 'destroy'])
            ->name('businesses.rewards.destroy');

        Route::prefix('businesses/{business}/analytics')->name('businesses.analytics.')->group(function (): void {
            Route::get('/overview', [ManageAnalyticsController::class, 'overview'])->name('overview');
            Route::get('/dashboard', [ManageAnalyticsController::class, 'dashboard'])->name('dashboard');
            Route::get('/reservations', [ManageAnalyticsController::class, 'reservations'])->name('reservations');
            Route::get('/revenue', [ManageAnalyticsController::class, 'revenue'])->name('revenue');
            Route::get('/resources', [ManageAnalyticsController::class, 'resources'])->name('resources');
            Route::get('/resources/ranking', [ManageAnalyticsController::class, 'resourceRanking'])->name('resources.ranking');
            Route::get('/resource-categories', [ManageAnalyticsController::class, 'resourceCategories'])->name('resource-categories');
            Route::get('/customers', [ManageAnalyticsController::class, 'customers'])->name('customers');
            Route::get('/top-customers', [ManageAnalyticsController::class, 'topCustomers'])->name('top-customers');
            Route::get('/peak-hours', [ManageAnalyticsController::class, 'peakHours'])->name('peak-hours');
            Route::get('/weekdays', [ManageAnalyticsController::class, 'weekdays'])->name('weekdays');
            Route::get('/hourly-occupancy', [ManageAnalyticsController::class, 'hourlyOccupancy'])->name('hourly-occupancy');
            Route::get('/check-ins', [ManageAnalyticsController::class, 'checkIns'])->name('check-ins');
            Route::get('/loyalty', [ManageAnalyticsController::class, 'loyalty'])->name('loyalty');
            Route::get('/promotions', [ManageAnalyticsController::class, 'promotions'])->name('promotions');
            Route::get('/referrals', [ManageAnalyticsController::class, 'referrals'])->name('referrals');
            Route::get('/reviews', [ManageAnalyticsController::class, 'reviews'])->name('reviews');
            Route::get('/favorites', [ManageAnalyticsController::class, 'favorites'])->name('favorites');
        });
    });
