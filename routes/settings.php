<?php

use App\Http\Controllers\CreditPurchaseController;
use App\Http\Controllers\Settings\CreditsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::get('settings/credits', CreditsController::class)->name('credits.index');

    Route::post('settings/credits/purchases', [CreditPurchaseController::class, 'store'])
        ->middleware('throttle:checkouts')
        ->name('credits.purchases.store');

    Route::middleware(['can:view,purchase', 'throttle:payment-checks'])->group(function () {
        Route::post('settings/credits/purchases/{purchase}/confirm', [CreditPurchaseController::class, 'confirm'])
            ->name('credits.purchases.confirm');
        Route::get('settings/credits/purchases/{purchase}/invoice', [CreditPurchaseController::class, 'invoice'])
            ->name('credits.purchases.invoice');
    });
});
