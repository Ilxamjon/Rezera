<?php

/**
 * Legacy profile routes — prefer `/api/v1/me/profile` for new clients.
 *
 * @deprecated Use routes in `routes/api/v1/customer.php` (`/me/profile`).
 */

use App\Http\Controllers\Api\V1\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('profile')->name('api.v1.profile.')->group(function (): void {
    Route::get('/', [ProfileController::class, 'show'])->name('show');
    Route::patch('/', [ProfileController::class, 'update'])->name('update');
});
