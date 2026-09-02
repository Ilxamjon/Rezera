<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Business Owner / Staff Routes (v1)
|--------------------------------------------------------------------------
|
| Business-scoped management endpoints.
| Authorization will use business membership policies.
|
*/

Route::middleware(['auth:sanctum'])->prefix('owner')->name('api.v1.owner.')->group(function (): void {
    // POST /owner/businesses — future
    // GET  /owner/businesses/{business}/reservations — future
});
