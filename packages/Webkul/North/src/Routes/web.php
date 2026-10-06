<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Webkul\North\Http\Controllers\NorthController;

Route::group(['middleware' => ['web']], function () {
    Route::prefix('north')->group(function () {
        Route::post('/create-session', [NorthController::class, 'createSession'])->name('shop.north.create-session');

        Route::post('/verify', [NorthController::class, 'verify'])->name('shop.north.verify');
    });
});

Route::post('north/webhook', [NorthController::class, 'webhook'])
    ->withoutMiddleware(VerifyCsrfToken::class)
    ->name('north.webhook');
