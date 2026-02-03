<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAccountActive
{

	public function handle(Request $request, Closure $next)
	{
		$realm = realm($request);

		$user = $request->user();
		if ($user && $user->status !== 'active')
		{
			Auth::guard($realm)->logout();
			$request->session()->invalidate();
			$request->session()->regenerateToken();

			$to = "{$realm}.login";
			return redirect()->route($to)->withErrors(['email' => 'Cuenta no activa.']);
		}
		return $next($request);
	}
}
