<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BindSessionToUser
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Detectamos el guard activo (admin o customer). Solo uno debería estar logueado.
        $guards = ['admin', 'customer']; // si quieres, usa array_keys(config('auth.guards'))
        $activeGuard = null;
        foreach ($guards as $g) {
            if (auth($g)->check()) { $activeGuard = $g; break; }
        }

        if ($activeGuard) {
            try {
                DB::table('sessions')
                    ->where('id', session()->getId())
                    ->update([
                        'user_id'      => auth($activeGuard)->id(),
                        'ip_address'   => $request->ip(),
                        'user_agent'   => Str::limit($request->userAgent() ?? '', 255),
                        'last_activity'=> time(),
                    ]);
            } catch (\Throwable $e) {
                // no-op (no romper la request por un fallo no crítico)
            }
        }

        return $response;
    }
}
