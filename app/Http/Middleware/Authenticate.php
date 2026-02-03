<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Route;

class Authenticate extends Middleware
{
    /**
     * Devuelve la URL a la que redirigir cuando no está autenticado.
     * Elegimos la pantalla de login según el realm detectado por SetRealm.
     */
    protected function redirectTo($request): ?string
    {
        // Peticiones API/JSON no deben redirigir
        if ($request->expectsJson()) {
            return null;
        }

        // 1) Intentar usar el helper global realm() si existe
        $realm = \App\Support\Realm::current();
		
        // 2) Resolver ruta de login según realm
		if(!empty($realm))
		{
			return route("{$realm}.login");
		}

        // 4) Fallback genérico (cuando no hay realm)
        return route('home');
    }
}
