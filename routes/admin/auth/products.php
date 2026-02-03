<?php

use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:admin.products.manage')
    ->prefix('products')
    ->name('products.')
    ->group(function () {

        Route::get('/', [ProductController::class, 'index'])
            ->name('index');

        Route::get('/{product}', [ProductController::class, 'show'])
            ->name('show');

        Route::post('/', [ProductController::class, 'store'])
            ->name('store');

        Route::put('/{product}', [ProductController::class, 'update'])
            ->name('update');
		
});
