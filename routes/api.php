<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rezera API Routes
|--------------------------------------------------------------------------
|
| All mobile API routes are versioned under /api/v1.
| Domain-specific route files are grouped by access level.
|
*/

Route::prefix('v1')->group(function (): void {
    require __DIR__.'/api/v1/public.php';
    require __DIR__.'/api/v1/businesses.php';
    require __DIR__.'/api/v1/payments.php';
    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/profile.php';
    require __DIR__.'/api/v1/manage.php';
    require __DIR__.'/api/v1/customer.php';
    require __DIR__.'/api/v1/admin.php';
});
