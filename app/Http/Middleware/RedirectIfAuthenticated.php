<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Si el usuario ya está autenticado en el guard indicado (guest:admin o guest:customer),
     * redirige al dashboard correspondiente.
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        // Si no pasan guard, asumimos el default (no recomendado)
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return match ($guard) {
                    'admin'    => redirect()->route('admin.home'),
                    'customer' => redirect()->route('customer.home'),
                    default    => redirect()->route('home'),
                };
            }
        }

        return $next($request);
    }
}
