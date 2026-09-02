<?php

use App\Http\Controllers\Api\V1\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('payments')->name('api.v1.payments.')->group(function (): void {
    Route::post('/webhooks/{provider}', [PaymentWebhookController::class, 'handle'])
        ->middleware('throttle:webhooks')
        ->name('webhooks.handle');
});
