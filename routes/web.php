<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\ListingCopyController;
use App\Http\Controllers\ListingExportController;
use App\Http\Controllers\ListingPhotoController;
use App\Http\Controllers\ListingPhotosArchiveController;
use App\Http\Controllers\ListingRetryController;
use App\Http\Controllers\ListingSourceController;
use App\Services\ListingGenerator;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome', [
    'signupCredits' => (int) config('vitrin.credits.signup'),
    'costs' => ListingGenerator::prices(),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('listings/create', [ListingController::class, 'create'])->name('listings.create');
    Route::post('listings', [ListingController::class, 'store'])
        ->middleware('throttle:generations')
        ->name('listings.store');

    Route::middleware('can:view,listing')->group(function () {
        Route::get('listings/{listing}', [ListingController::class, 'show'])->name('listings.show');
        Route::get('listings/{listing}/source', ListingSourceController::class)->name('listings.source');
        Route::get('listings/{listing}/export', ListingExportController::class)->name('listings.export');
        Route::get('listings/{listing}/photos/download', ListingPhotosArchiveController::class)->name('listings.photos.download');
        Route::get('listings/{listing}/photos/{photo}', [ListingPhotoController::class, 'show'])
            ->scopeBindings()
            ->name('listings.photos.show');
    });

    Route::middleware(['can:update,listing', 'throttle:generations'])->scopeBindings()->group(function () {
        Route::post('listings/{listing}/retry', ListingRetryController::class)->name('listings.retry');
        Route::post('listings/{listing}/copies/{copy}/regenerate', [ListingCopyController::class, 'regenerate'])
            ->name('listings.copies.regenerate');
        Route::post('listings/{listing}/photos/{photo}/regenerate', [ListingPhotoController::class, 'regenerate'])
            ->name('listings.photos.regenerate');
    });

    Route::delete('listings/{listing}', [ListingController::class, 'destroy'])
        ->middleware('can:delete,listing')
        ->name('listings.destroy');
});

require __DIR__.'/settings.php';
