<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Country;

class Select2DemoController extends Controller
{
	public function index()
	{
		$countries = Country::query()
			->orderBy('iso2')
			->get([
				'id',
				'iso2',
				'iso3',          // necesario para el 3er ejemplo (mapa value=>label)
				'name',
				'continent_code',
			]);

		// Mapa ISO3 => nombre para el 3er ejemplo
		$countriesIso3Map = $countries
			->whereNotNull('iso3')
			->pluck('rawName', 'iso3')
			->toArray();

		return view('dev.select2-demo', [
			'countries'       => $countries,
			'countriesIso3Map'=> $countriesIso3Map,
		]);
	}
}
