<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\Auth\LoginController;
use App\Http\Controllers\Api\V1\Admin\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/admin')->name('admin.')->middleware('trusted.origin')->group(function (): void {
    Route::post('auth/login', LoginController::class)->name('auth.login');

    Route::middleware(['admin.jwt', 'admin.throttle'])->group(function (): void {
        Route::post('auth/logout', LogoutController::class)->name('auth.logout');

        if (app()->environment(['local', 'testing'])) {
            Route::apiResource('products', ProductController::class)->except('update');
            Route::patch('products/{product}', [ProductController::class, 'update'])->name('products.update');
        }
    });
});
