<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\Admin\WelcomeAdmin;
use App\Notifications\Customer\WelcomeCustomer;
use App\Support\PasswordHistoryService;
use App\Support\Realm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UserObserver
{

	/**
	 * Enviar email de bienvenida al crear usuario
	 */
	public function created(User $user): void
	{
		try
		{
			// Si estás sembrando datos y NO quieres correos, descomenta:
			// if (app()->runningInConsole() && ! app()->runningUnitTests()) return;
			// Contraseña temporal (16 chars, mezcla de tipos)
			$tempPwd = self::makeTempPassword(8);

			// Guardar password + forzar cambio
			$user->forceFill([
				'password'				=> $tempPwd, // usa cast 'hashed' en el modelo
				'force_password_change' => true, // requiere migración/columna
			])->save();

			// Enviar notificación de bienvenida (por realm)
			if ($user->realm === Realm::ADMIN)
			{
				$user->notify(new WelcomeAdmin(
								loginUrl: route('admin.login'),
								tempPassword: $tempPwd
				));
			}
			elseif ($user->realm === Realm::CUSTOMER)
			{
				$user->notify(new WelcomeCustomer(
								loginUrl: route('customer.login'),
								tempPassword: $tempPwd
				));
			}
		}
		catch (Throwable $e)
		{
			Log::warning('Welcome mail failed', [
				'user_id' => $user->id,
				'msg'	  => $e->getMessage(),
			]);
		}
	}

	/**
	 * Revocar sesiones cuando cambie estado o contraseña
	 */
	public function updated(User $user): void
	{
		// Si cambió el status => revocar TODAS las sesiones
		if ($user->wasChanged('status'))
		{
			try
			{
				DB::table('sessions')->where('user_id', $user->id)->delete();
			}
			catch (Throwable $e)
			{
				Log::warning('Session revoke (status) failed', ['user_id' => $user->id, 'msg' => $e->getMessage()]);
			}
		}

		// Recordar el hash anterior si cambió la contraseña
		if ($user->wasChanged('password'))
		{
			try
			{
				app(PasswordHistoryService::class)->remember($user, $user->getOriginal('password'));
			}
			catch (Throwable $e)
			{
				Log::warning('PasswordHistoryService (remember) failed', ['user_id' => $user->id, 'msg' => $e->getMessage()]);
			}

			try
			{
				$currentSid = session()->getId();
				\DB::table('sessions')
						->where('user_id', $user->id)
						->when($currentSid, fn($q) => $q->where('id', '!=', $currentSid))
						->delete();
			}
			catch (Throwable $e)
			{
				Log::warning('Expirar sesiones por cambio de clave failed', ['user_id' => $user->id, 'msg' => $e->getMessage()]);
			}
		}
	}

	public static function makeTempPassword(int $len = 16): string
	{
		if ($len <= 0)
		{
			return '';
		}

		$letters = 'abcdef';
		$digits	 = '0123456789';

		$out		= '';
		$useLetters = true; // empieza con 4 letras

		while (strlen($out) < $len)
		{
			$pool = $useLetters ? $letters : $digits;

			$remaining = $len - strlen($out);
			$chunkLen  = min(3, $remaining);

			for ($i = 0; $i < $chunkLen; $i++)
			{
				$out .= $pool[random_int(0, strlen($pool) - 1)];
			}

			$useLetters = !$useLetters; // alterna: letras -> números -> letras -> ...
		}

		return $out;
	}
}
