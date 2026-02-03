<?php

namespace App\Http\Middleware;

use App\Support\Realm;
use Closure;
use Illuminate\Http\Request;

class SetRealm
{

	/**
	 * Uso: ->middleware('realm:admin') o ->middleware('realm:customer')
	 */
	public function handle(Request $request, Closure $next, string $realm)
	{
		$normalized = strtolower($realm);
		$current	= Realm::isValid($normalized) ? $normalized : null;

		// Guardamos en el Request (fuente de verdad en tiempo de request)
		$request->attributes->set(Realm::ATTRIBUTE, $current);

		// Cacheamos también para otros usos puntuales
		Realm::setCurrent($current);

		return $next($request);
	}
}
