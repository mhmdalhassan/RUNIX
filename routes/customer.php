<?php

use App\Http\Controllers\Customer\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Customer\Auth\NewPasswordController;
use App\Http\Controllers\Customer\Auth\RegisteredCustomerController;
use App\Http\Controllers\Customer\CompleteCustomerProfileController;
use App\Http\Controllers\Customer\OrderController;
use Illuminate\Support\Facades\Route;

// The customer-facing site's own auth — a fully separate guard/identity

Route::prefix('customer')->name('customer.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('register', [RegisteredCustomerController::class, 'create'])->name('register');
        Route::post('register', [RegisteredCustomerController::class, 'store']);

        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    });

    Route::middleware('auth:customer')->group(function () {
  
        Route::middleware('customer.profile.redirect-if-complete')->group(function () {
            Route::get('complete-profile', [CompleteCustomerProfileController::class, 'edit'])->name('complete-profile.edit');
            Route::put('complete-profile', [CompleteCustomerProfileController::class, 'update'])->name('complete-profile.update');
        });

        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::middleware('customer.profile.require-complete')->group(function () {
            Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
        });
    });
});
