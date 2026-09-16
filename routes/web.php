<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'api' => '/api/v1/health',
        'privacy' => url('/privacy'),
        'admin' => url('/admin'),
    ]);
});

Route::view('/privacy', 'legal.privacy')->name('privacy');
