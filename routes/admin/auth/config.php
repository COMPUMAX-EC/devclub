<?php

use App\Http\Controllers\Admin\ConfigController;
use Illuminate\Support\Facades\Route;

Route::prefix('config')
    ->name('config.')
	->group(function () {
        Route::get('', [ConfigController::class, 'index'])->name('index');
        Route::get('{item}', [ConfigController::class, 'show'])->name('show');

        Route::post('config', [ConfigController::class, 'store'])->name('store')->middleware('can:admin.config.create');
		
        Route::put('{item}/definition', [ConfigController::class, 'updateDefinition'])->name('update-definition')->middleware('can:admin.config.edit');
        Route::put('{item}/value', [ConfigController::class, 'updateValue'])->name('update-value')->middleware('can:admin.config.fill');

        Route::post('{item}/file', [ConfigController::class, 'uploadFile'])->name('upload-file')->middleware('can:admin.config.fill');

        Route::delete('{item}', [ConfigController::class, 'destroy'])->name('destroy')->middleware('can:admin.config.delete');
    });
