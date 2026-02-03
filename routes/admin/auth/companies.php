<?php

use App\Http\Controllers\Admin\CompanyCommissionUserController;
use App\Http\Controllers\Admin\CompanyController;
use Illuminate\Support\Facades\Route;

// Prefijo /admin y middleware de autenticación/realm vienen del grupo padre en routes/admin.php
Route:://middleware('can:admin.companies.manage')
		prefix('companies')
		->name('companies.')
		->controller(CompanyController::class)
		->group(function ()
		{
			// Listado + JSON para Vue
			Route::get('/', 'index')->name('index');
			Route::get('/check-short-code', 'checkShortCode')->name('check-short-code');
			Route::post('/', 'store')->name('store');

			Route::prefix('{company}')
					->group(function ()
					{

						Route::get('/edit', 'edit')->name('edit');
						Route::get('', 'show')->name('show');

						// AJAX para modal de usuarios
						Route::get('/users/search', 'searchUsers')->name('users.search');
						Route::post('/users/{user}', 'attachUser')->name('users.attach');
						Route::delete('/users/{user}', 'detachUser')->name('users.detach');

						Route::put('', 'update')->name('update');
						Route::put('/suspend', 'suspend')->name('suspend');
						Route::put('/archive', 'archive')->name('archive');
						Route::put('/activate', 'activate')->name('activate');

						Route::get('/commission-users', [CompanyCommissionUserController::class, 'index'])
								->name('commission-users.index');

						Route::get('/commission-users/available', [CompanyCommissionUserController::class, 'available'])
								->name('commission-users.available');

						Route::post('/commission-users', [CompanyCommissionUserController::class, 'store'])
								->name('commission-users.store');

						Route::patch('/commission-users/{commissionUser}', [CompanyCommissionUserController::class, 'update'])
								->name('commission-users.update');

						Route::delete('/commission-users/{commissionUser}', [CompanyCommissionUserController::class, 'destroy'])
								->name('commission-users.destroy');
						Route::get('/capitados', 'capitatedProducts')->name('capitated-products.index');
					});
		});
