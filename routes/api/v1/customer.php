<?php

use App\Http\Controllers\Api\V1\Me\BusinessInvitationController;
use App\Http\Controllers\Api\V1\Me\DeviceController;
use App\Http\Controllers\Api\V1\Me\FavoriteBusinessController;
use App\Http\Controllers\Api\V1\Me\LoyaltyController as MeLoyaltyController;
use App\Http\Controllers\Api\V1\Me\NotificationController as MeNotificationController;
use App\Http\Controllers\Api\V1\Me\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\Me\PaymentController as MePaymentController;
use App\Http\Controllers\Api\V1\Me\ProfileController as MeProfileController;
use App\Http\Controllers\Api\V1\Me\ReferralController as MeReferralController;
use App\Http\Controllers\Api\V1\Me\ReservationCheckInController as MeReservationCheckInController;
use App\Http\Controllers\Api\V1\Me\ReservationController as MeReservationController;
use App\Http\Controllers\Api\V1\Me\ReservationReviewController as MeReservationReviewController;
use App\Http\Controllers\Api\V1\Me\ReviewController as MeReviewController;
use App\Http\Controllers\Api\V1\Me\SavedSearchController as MeSavedSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('me')->name('api.v1.me.')->group(function (): void {
    Route::get('/business-invitations', [BusinessInvitationController::class, 'index'])->name('business-invitations.index');
    Route::post('/business-invitations/{invitation}/accept', [BusinessInvitationController::class, 'accept'])->name('business-invitations.accept');
    Route::post('/business-invitations/{invitation}/decline', [BusinessInvitationController::class, 'decline'])->name('business-invitations.decline');

    Route::get('/profile', [MeProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile', [MeProfileController::class, 'update'])->name('profile.update');

    Route::get('/loyalty', [MeLoyaltyController::class, 'show'])->name('loyalty.show');
    Route::get('/loyalty/transactions', [MeLoyaltyController::class, 'transactions'])->name('loyalty.transactions');
    Route::get('/loyalty/redemptions', [MeLoyaltyController::class, 'redemptions'])->name('loyalty.redemptions');

    Route::get('/referral', [MeReferralController::class, 'show'])->name('referral.show');
    Route::get('/referrals', [MeReferralController::class, 'index'])->name('referrals.index');

    Route::get('/favorites/businesses', [FavoriteBusinessController::class, 'index'])->name('favorites.businesses.index');
    Route::post('/favorites/businesses/{business}', [FavoriteBusinessController::class, 'store'])->name('favorites.businesses.store');
    Route::delete('/favorites/businesses/{business}', [FavoriteBusinessController::class, 'destroy'])->name('favorites.businesses.destroy');

    Route::get('/saved-searches', [MeSavedSearchController::class, 'index'])->name('saved-searches.index');
    Route::post('/saved-searches', [MeSavedSearchController::class, 'store'])->middleware('throttle:saved-searches')->name('saved-searches.store');
    Route::get('/saved-searches/{savedSearch}', [MeSavedSearchController::class, 'show'])->name('saved-searches.show');
    Route::patch('/saved-searches/{savedSearch}', [MeSavedSearchController::class, 'update'])->middleware('throttle:saved-searches')->name('saved-searches.update');
    Route::delete('/saved-searches/{savedSearch}', [MeSavedSearchController::class, 'destroy'])->name('saved-searches.destroy');

    Route::get('/reviews', [MeReviewController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}', [MeReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [MeReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/reservations/upcoming', [MeReservationController::class, 'upcoming'])->name('reservations.upcoming');
    Route::get('/reservations', [MeReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations/{reservation}/payments', [MePaymentController::class, 'store'])->name('reservations.payments.store');
    Route::get('/reservations/{reservation}', [MeReservationController::class, 'show'])->name('reservations.show');
    Route::post('/reservations/{reservation}/cancel', [MeReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('/reservations/{reservation}/check-in', [MeReservationCheckInController::class, 'checkIn'])->name('reservations.check-in');
    Route::post('/reservations/{reservation}/check-in/qr', [MeReservationCheckInController::class, 'checkInQr'])->middleware('throttle:booking')->name('reservations.check-in.qr');
    Route::post('/reservations/{reservation}/check-out', [MeReservationCheckInController::class, 'checkOut'])->name('reservations.check-out');
    Route::post('/reservations/{reservation}/review', [MeReservationReviewController::class, 'store'])->name('reservations.review.store');
    Route::get('/payments/{payment}', [MePaymentController::class, 'show'])->name('payments.show');

    Route::get('/notifications/unread-count', [MeNotificationController::class, 'unreadCount'])->name('notifications.unread-count');
    Route::post('/notifications/read-all', [MeNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications', [MeNotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}', [MeNotificationController::class, 'show'])->name('notifications.show');
    Route::patch('/notifications/{notification}/read', [MeNotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index'])->name('notification-preferences.index');
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

    Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::patch('/devices/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
});

Route::middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/reviews/{review}/report', [\App\Http\Controllers\Api\V1\ReviewReportController::class, 'store'])
        ->name('api.v1.reviews.report');
});
