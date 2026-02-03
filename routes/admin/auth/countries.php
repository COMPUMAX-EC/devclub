<?php

use App\Http\Controllers\Admin\CountryController;
use Illuminate\Support\Facades\Route;

// Prefijo /admin y middleware de autenticación/realm vienen del grupo padre en routes/admin.php
Route::middleware('can:admin.countries.manage')
	->prefix('countries')
    ->name('countries.')
	->group(function () {
    Route::get('/', [CountryController::class, 'index'])
        ->name('index');

    Route::get('/{country}', [CountryController::class, 'show'])
        ->name('show');

    Route::post('/', [CountryController::class, 'store'])
        ->name('store');

    Route::put('/{country}', [CountryController::class, 'update'])
        ->name('update');

    Route::put('/{country}/toggle-active', [CountryController::class, 'toggleActive'])
        ->name('toggle-active');
});