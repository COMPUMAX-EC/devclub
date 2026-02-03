<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Admin\ResetPasswordAdmin;
use App\Notifications\Customer\ResetPasswordCustomer;
use App\Support\PasswordHistoryService;
use App\Support\PasswordPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PasswordController extends Controller
{

	/**
	 * Política de contraseñas para el frontend (JSON).
	 * Endpoint público (no requiere auth).
	 */
	public function policy(PasswordPolicy $policy): JsonResponse
	{
		return response()->json($policy->forFrontend());
	}

	public function changeSelf(Request $request)
	{
		$realm = realm($request);
		$user = auth($realm)->user();
		abort_unless($user, 403);

		$data = $request->validate([
			'current_password'		=> ['required', 'string'],
			'password'				=> ['required', 'confirmed', 'string'],
			'password_confirmation' => ['required', 'string'],
		]);

		if (!\Hash::check($data['current_password'], $user->password))
		{
			return back()->withErrors(['current_password' => __('La contraseña actual no es válida.')]);
		}

		// Política central (ya la tienes)...
		$policyRules = app(PasswordPolicy::class)->rule([
			'first_name'   => $user->first_name,
			'last_name'	   => $user->last_name,
			'display_name' => $user->display_name,
			'email'		   => $user->email,
		]);
		$v			 = Validator::make(['password' => $data['password']], ['password' => array_merge(['required'], $policyRules)]);
		if ($v->fails())
		{
			return back()->withErrors($v);
		}

		// Reuso: bloquea si coincide con actual o con últimas N
		if (app(PasswordHistoryService::class)->reused($user, $data['password']))
		{
			return back()->withErrors(['password' => __('No puedes reutilizar una contraseña reciente.')]);
		}

		// Guardar (Observer recordará el hash anterior y revocará otras sesiones)
		$user->forceFill([
			'password'		 => $data['password'],
			'remember_token' => \Str::random(60),
		])->save();

		// Como refuerzo, por UX deja solo la sesión actual (esto ya lo hace Observer).
		return back()->with('status', __('Contraseña actualizada.'));
	}

	/**
	 * Chequeo/validación de una contraseña "en vivo" desde el frontend.
	 * Útil para mostrar errores antes de enviar formularios.
	 */
	public function check(Request $request, PasswordPolicy $policy): JsonResponse
	{
		$data = $request->validate([
			'password'	   => ['required', 'string'],
			'first_name'   => ['nullable', 'string'],
			'last_name'	   => ['nullable', 'string'],
			'display_name' => ['nullable', 'string'],
			'email'		   => ['nullable', 'email'],
		]);

		$rules = array_merge(['required'], $policy->rule($data));
		$v	   = Validator::make(['password' => $data['password']], ['password' => $rules]);

		return response()->json([
					'valid'	 => $v->passes(),
					'errors' => $v->errors()->get('password'),
		]);
	}

	// --- Forgot / Reset (público) ---
	public function requestForm(Request $request)
	{
		$realm = realm($request);
		return view($this->view('forgot', $realm));
	}

	public function emailLink(Request $request)
	{
		$realm = realm($request);

		$request->validate(['email' => 'required|email']);

		$status = Password::broker($realm)->sendResetLink(
				['email' => Str::lower($request->email), 'realm' => $realm],
				function ($user, string $token) use ($realm)
				{
					$url = route($this->passwordResetRoute($realm), [
						'token' => $token,
						'email' => $user->email,
					]);

					// Notificación propia por realm
					$notification = $this->makeResetNotification($realm, $url);

					// ¿Async (cola) o instantáneo? config/notify.php
					$async = config('notify.password_reset.async');
					if ($async === null)
					{
						$async = config('notify.mail.async');
					}

					$async ? $user->notify($notification) : $user->notifyNow($notification);
				}
		);

		return $status === Password::RESET_LINK_SENT ? back()->with('status', __($status)) : back()->withErrors(['email' => __($status)]);
	}

	/**
	 * Muestra el formulario solo si el token es válido.
	 */
	public function showResetForm(Request $request, string $token)
	{
		$realm = realm($request);
		$email = (string) $request->query('email', '');

		if (!$email || !$this->isTokenValid($realm, $email, $token))
		{
			return $this->invalidTokenView($realm);
		}

		return view($realm . '.auth.reset', [
			'token' => $token,
			'email' => $email,
		]);
	}

	/**
	 * Procesa el cambio de contraseña. Si el token expiró o es inválido,
	 * muestra la vista de token inválido y redirige al login del realm.
	 */
	public function reset(Request $request)
	{
		$realm		= realm($request);
		$loginRoute = "{$realm}.login";

		$request->validate([
			'token'					=> ['required'],
			'email'					=> ['required', 'email'],
			'password'				=> ['required', 'confirmed', 'string'],
			'password_confirmation' => ['required', 'string'],
		]);

		// 1) Si el usuario existe, valida contra su historial
		if ($user = User::where('email', strtolower($request->email))
				->where('realm', $realm)
				->first())
		{
			if (app(PasswordHistoryService::class)->reused($user, $request->password))
			{
				return back()
								->withErrors(['password' => __('No puedes reutilizar una contraseña reciente.')])
								->withInput(['email' => $request->email]);
			}
		}

		// 2) Ejecuta el reset
		$status = \Password::broker($realm)->reset(
				$request->only('email', 'password', 'password_confirmation', 'token'),
				function ($user, $password)
				{
					// El Observer guardará el hash anterior automáticamente
					$user->forceFill([
						'password'			   => $password, // cast 'hashed' en el modelo
						'remember_token'	   => \Str::random(60),
						'force_password_change' => false,
					])->save();

					// En recovery queremos revocar TODAS las sesiones
					try
					{
						\DB::table('sessions')->where('user_id', $user->id)->delete();
					}
					catch (Throwable $e)
					{
						
					}
				}
		);

		if ($status === \Password::PASSWORD_RESET)
		{
			return response()->view($realm . '.auth.reset_success', [
						'loginUrl' => route($loginRoute),
						'status'   => __('Contraseña actualizada correctamente.'),
			]);
		}

		return $this->invalidTokenView($realm);
	}

	// === NUEVO: Cambio forzado tras login ===
	public function forceEdit(Request $request)
	{
		$realm = realm($request);
		return view($realm . '.auth.force_password');
	}

	public function forceUpdate(Request $request)
	{
		$realm = realm($request);

		$user = auth($realm)->user();

		$request->validate([
			'current_password'		=> ['required', 'string'],
			'password'				=> ['required', 'confirmed', 'string'],
			'password_confirmation' => ['required', 'string'],
		]);

		// Verificar actual
		if (!Hash::check($request->input('current_password'), $user->password))
		{
			return back()->withErrors(['current_password' => __('Contraseña actual incorrecta.')]);
		}

		// Reglas avanzadas (tu PasswordPolicy)
		$policy = app(\App\Support\PasswordPolicy::class);
		$ctx	= [
			'first_name'   => $user->first_name,
			'last_name'	   => $user->last_name,
			'display_name' => $user->display_name,
			'email'		   => $user->email,
		];
		$rules	= array_merge(['required'], $policy->rule($ctx));

		Validator::make(
				['password' => $request->input('password')],
				['password' => $rules]
		)->validate();

		// Guardar nueva pass + limpiar flag
		$user->forceFill([
			'password'			   => $request->input('password'),
			'remember_token'	   => Str::random(60),
			'force_password_change' => false, // <-- UNIFICADO AQUÍ
		])->save();

		// Mantener la sesión actual; opcionalmente, puedes revocar otras sesiones del mismo user:
		try
		{
			DB::table('sessions')
					->where('user_id', $user->id)
					->where('id', '<>', $request->session()->getId())
					->delete();
		}
		catch (\Throwable $e)
		{
			
		}

		// Redirigir al dashboard del realm
		$dashboard = $realm === 'admin' ? 'admin.home' : 'customer.home';
		return redirect()->route($dashboard)->with('status', __('Contraseña actualizada.'));
	}

	/**
	 * Vista de token inválido/expirado con redirección automática al login del realm.
	 */
	private function invalidTokenView(string $realm)
	{
		$loginRoute = "{$realm}.login";

		return response()->view($realm . '.auth.reset_invalid', [
					'loginUrl' => route($loginRoute),
					'message'  => __('El enlace para restablecer la contraseña no es válido o ha expirado.'),
						], 410); // 410 Gone es semánticamente correcto
	}

	/**
	 * Valida el token contra la tabla configurada del broker del realm,
	 * comparando hash y vigencia (expire en minutos).
	 */
	private function isTokenValid(string $realm, string $email, string $plainToken): bool
	{
		$table	 = (string) config("auth.passwords.$realm.table", 'password_reset_tokens');
		$expireM = (int) config("auth.passwords.$realm.expire", 60);

		$row = DB::table($table)->where('email', strtolower($email))->first();
		if (!$row || empty($row->token))
		{
			return false;
		}

		// En Laravel moderno el token en DB está hasheado (Hash::make)
		$hashOk = Hash::check($plainToken, $row->token);
		if (!$hashOk)
		{
			return false;
		}

		// Vigencia por created_at + expire (minutos)
		$created = $row->created_at ? Carbon::parse($row->created_at) : null;
		if (!$created)
		{
			return false;
		}

		return $created->copy()->addMinutes($expireM)->isFuture();
	}

	// --------- helpers ----------
	private function passwordResetRoute(string $realm): string
	{
		return "{$realm}.password.reset";
	}

	private function view(string $name, string $realm): string
	{
		return "{$realm}.auth.{$name}";
	}

	private function makeResetNotification(string $realm, string $url): object
	{
		return $realm === 'customer' ?
				new ResetPasswordCustomer($url, minutes: config('auth.passwords.customer.expire', 30)) :
				new ResetPasswordAdmin($url, minutes: config('auth.passwords.admin.expire', 30));
	}
}
