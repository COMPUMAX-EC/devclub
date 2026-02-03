<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Realm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;


class LoginController extends Controller
{

	public function showLogin(Request $request)
	{
		$realm = Realm::current();
		return view("{$realm}.auth.login");
	}

	public function login(Request $request)
	{
		$realm = Realm::current();

		$request->validate([
			'email'	   => ['required', 'email'],
			'password' => ['required', 'string'],
		]);

		$key = sprintf('login:%s|%s|%s', Str::lower($request->email), $request->ip(), $realm);
		if (RateLimiter::tooManyAttempts($key, 5))
		{
			return back()->withErrors(['email' => 'Demasiados intentos. Prueba más tarde.']);
		}

		$ok = Auth::guard($realm)->attempt([
			'email'	   => $request->email,
			'password' => $request->password,
			'realm'	   => $realm,
		]);

		if ($ok)
		{
			$request->session()->regenerate();
			RateLimiter::clear($key);

			$user = Auth::guard($realm)->user();
			$user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

            // Si debe cambiar contraseña => forzar flujo
            if ($user->forcePasswordChange())
			{
				$route = "{$realm}.password.force.edit";
                return redirect()->route($route)->with('status', __('Debes actualizar tu contraseña antes de continuar.'));
            }
			
			return redirect()->intended(route("{$realm}.home"));
		}

		RateLimiter::hit($key, 900); // 15 minutos
		return back()
						->withErrors(['email' => 'Credenciales inválidas o cuenta no activa.'])
						->onlyInput('email');
	}

	public function logout(Request $request)
	{
		$realm = Realm::current();

		if ($realm)
		{
			auth($realm)->logout();
		}
		else
		{
			// fallback: desloguear ambos
			auth(Realm::ADMIN)->logout();
			auth(Realm::CUSTOMER)->logout();
		}

		$request->session()->invalidate();
		$request->session()->regenerateToken();

		return redirect()->route("{$realm}.login");
	}

    protected function dashboardRouteFor(?string $realm): string
    {
        return "{$realm}.home";
    }

}
