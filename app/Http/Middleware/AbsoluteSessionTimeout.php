<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;

class AbsoluteSessionTimeout
{

	public function handle(Request $request, Closure $next)
	{
		$realm = realm($request);
		$maxMinutes = SystemSetting::get('auth.session.absolute_timeout_minutes', 600) ?? 600; // 10h por defecto
		$started	= session('abs_start_ts');
		if (!$started)
		{
			session(['abs_start_ts' => now()->getTimestamp()]);
		}
		else
		{
			if ((now()->getTimestamp() - $started) > ($maxMinutes * 60))
			{
				Auth::guard($realm)->logout();
				$request->session()->invalidate();
				$request->session()->regenerateToken();
				$to = "{$realm}.login";

				return redirect()->route($to)->with('info', 'Tu sesión expiró por tiempo máximo.');
			}
		}
		return $next($request);
	}
}
