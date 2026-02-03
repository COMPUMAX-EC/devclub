<?php

use App\Http\Controllers\Admin\ZoneController;
use Illuminate\Support\Facades\Route;

// Prefijo /admin y middleware de autenticación/realm vienen del grupo padre en routes/admin.php
Route:://middleware('can:admin.countries.manage')
		prefix('zones')
		->name('zones.')
		->group(function ()
		{
			Route::get('/', [ZoneController::class, 'index'])
					->name('index');

			Route::get('/{zone}', [ZoneController::class, 'show'])
					->name('show');

			Route::post('/zones', [ZoneController::class, 'store'])
					->name('store');

			Route::put('/{zone}', [ZoneController::class, 'update'])
					->name('update');

			Route::put('/{zone}/toggle-active', [ZoneController::class, 'toggleActive'])
					->name('toggle-active');

			// Países dentro de una zona
			Route::get('/{zone}/countries', [ZoneController::class, 'countries'])
					->name('countries.index');

			Route::get('/{zone}/countries/available', [ZoneController::class, 'availableCountries'])
					->name('countries.available');

			Route::post('/{zone}/countries/{country}', [ZoneController::class, 'attachCountry'])
					->name('countries.attach');

			Route::delete('/{zone}/countries/{country}', [ZoneController::class, 'detachCountry'])
					->name('countries.detach');
		});
