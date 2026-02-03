<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Policies\AdminUserPolicy;
use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Permission;
use App\Support\FormatService;

class AppServiceProvider extends ServiceProvider
{

	public function register(): void
	{
		$this->loadHelpers();
		$this->app->singleton(PasswordPolicy::class, fn() => new PasswordPolicy());
		$this->app->singleton(FormatService::class, fn() => new FormatService());

        $this->app->singleton(GeoIpDatabaseManager::class);
        $this->app->singleton(IpCountryService::class);
	}

	public function boot(): void
	{
		Schema::defaultStringLength(191);

		// Observers
		User::observe(UserObserver::class);

		// Policies
		Gate::policy(User::class, AdminUserPolicy::class);

		/**
		 * Reglas globales de autorización:
		 * 1) Superadmin (permiso identidad) pasa siempre.
		 * 2) Para abilities "clásicos" de Policy, dejamos que la Policy decida.
		 * 3) Para cualquier otro ability, si existe como permiso Spatie en la BD y el usuario lo tiene, permitir.
		 * 4) En otro caso, no forzamos nada (null => sigue el flujo normal y terminará en deny si corresponde).
		 */
		Gate::before(function (User $user, string $ability)
		{
			// 1) Bypass total para superadmin por permiso-llave (sin usar Gate para evitar recursión)
			if ($user->hasPermissionTo('rbac.superadmin.identity', 'admin'))
			{
				return true;
			}

			// 2) Dejar que la Policy resuelva los abilities "clásicos"
			static $policyAbilities = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];
			if (in_array($ability, $policyAbilities, true))
			{
				return null;
			}

			// 3) Si el ability existe como permiso en Spatie, resolver por permiso
			if (Permission::query()->where('name', $ability)->where('guard_name', 'admin')->exists())
			{
				return $user->hasPermissionTo($ability, 'admin') ? true : null;
			}

			// 4) No forzar nada
			return null;
		});
	}

	protected function loadHelpers(): void
	{

		// cualquier helper suelto dentro de app/Support/helpers/*.php
		$pattern = app_path('Support/Helpers/*.php');
		$files	 = glob($pattern) ?: [];

		foreach ($files as $file)
		{
			if (is_file($file))
			{
				require_once $file;
			}
		}
	}
}
