<?php

namespace App\Http\Middleware;

use App\Support\Realm;
use Closure;
use Illuminate\Http\Request;

class ForcePasswordChange
{
    /**
     * Bloquea todo el sitio (ya autenticado) si el usuario debe cambiar su password,
     * excepto las rutas de cambio de password y logout.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        if (! $user || ! $user->forcePasswordChange()) {
            return $next($request);
        }

        // Permitir solo: ver/enviar formulario de cambio forzado y logout
        if (
            $request->routeIs('admin.password.force.*') ||
            $request->routeIs('customer.password.force.*') ||
            $request->routeIs('admin.logout') ||
            $request->routeIs('customer.logout')
        ) {
            return $next($request);
        }

        // Redirigir al formulario de cambio forzado según realm
        $route = Realm::isAdmin() ? 'admin.password.force.edit' : 'customer.password.force.edit';
        return redirect()->route($route)->with('status', __('Debes actualizar tu contraseña antes de continuar.'));
    }
}
