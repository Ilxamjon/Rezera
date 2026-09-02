<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ReferralValidationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.v1.health');

Route::get('/config', [\App\Http\Controllers\Api\V1\ConfigController::class, 'index'])->name('api.v1.config');

Route::get('/subscription-plans', [\App\Http\Controllers\Api\V1\SubscriptionPlanController::class, 'index'])
    ->name('api.v1.subscription-plans.index');
Route::get('/subscription-plans/{plan}', [\App\Http\Controllers\Api\V1\SubscriptionPlanController::class, 'show'])
    ->name('api.v1.subscription-plans.show');

Route::post('/referrals/validate', [ReferralValidationController::class, 'store'])
    ->name('api.v1.referrals.validate');
