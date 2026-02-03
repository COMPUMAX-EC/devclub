<?php

use App\Http\Middleware\AbsoluteSessionTimeout;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\BindSessionToUser;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SetRealm;
use App\Support\Realm;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$builder = Application::configure(basePath: dirname(__DIR__))
	->withRouting(
		web: __DIR__ . '/../routes/web.php', // público genérico
		api: __DIR__ . '/../routes/api_generic.php', // API / webhooks (usa 'api' middleware y prefijo /api)
		commands: __DIR__ . '/../routes/console.php',
		health: '/up'
	)
	->withMiddleware(function (Middleware $middleware)
	{
		// Aliases de middlewares personalizados
		$middleware->alias([
			'active'				   => EnsureAccountActive::class,
			'bind.session.user'		   => BindSessionToUser::class,
			'guest'					   => RedirectIfAuthenticated::class,
			'force.password.change'	   => ForcePasswordChange::class,
			'realm'					   => SetRealm::class,
			'auth'					   => Authenticate::class,
			'absolute.session.timeout' => AbsoluteSessionTimeout::class,
		]);

		// Prioridad: lo que esté antes corre antes, sin importar el orden en la ruta.
		$middleware->priority([
			// (conserva los que ya tengas priorizados; aquí solo muestro los relevantes)
			SetRealm::class,           // << debe ir antes
			\Illuminate\Session\Middleware\StartSession::class,
			\Illuminate\View\Middleware\ShareErrorsFromSession::class,
			Authenticate::class,       // << va después
		]);

		// Grupos reutilizables (incluyen 'web' para manejo de sesión/errores)
		$middleware->group('admin', [
			'web',
			'realm:admin', // para indicar que estamos en el realm admin y tenerlo como variable global en los controladores
			'auth:admin', // para poder manejar a nivel de autenticación el realm
			'active', // solo permitir usuarios activos
			'bind.session.user',
			'force.password.change', // si la clave ha expirado, fuerza cambiarla
			'absolute.session.timeout', // para expirar la sesion del usuario si ha pasado de tiempo
		]);

		$middleware->group('admin_guest', [
			'web',
			'realm:admin',
			'guest:admin',
		]);

		$middleware->group('customer', [
			'web',
			'realm:customer',
			'auth:customer',
			'active',
			'bind.session.user',
			'force.password.change',
			'absolute.session.timeout',
		]);

		$middleware->group('customer_guest', [
			'web',
			'realm:customer',
			'guest:customer',
		]);

		$middleware->redirectGuestsTo(function (Request $request)
		{
			if (Realm::isAdmin($request))
			{
				return route('admin.login');
			}
			if (Realm::isCustomer($request))
			{
				return route('customer.login');
			}

			return route('home'); // o la que prefieras como pública
		});
	})
	->withExceptions(function (Exceptions $exceptions)
	{
		// Personaliza manejo de excepciones aquí si lo necesitas.
	});

$app = $builder->create();

/**
 * Cambiar el storage path completo (storage/, logs, cache, sessions, etc.)
 * desde .env usando una ruta RELATIVA a la raíz del proyecto.
 *
 * Se ejecuta justo después de cargar .env y antes de cargar config.
 */
$app->afterBootstrapping(LoadEnvironmentVariables::class, function (Application $app)
{
	$new_route = env('APP_STORAGE_DIR', null);
	if(!empty($new_route))
	{
		$relative = trim((string) $new_route, "/\\");
		$app->useStoragePath($app->basePath($relative));
	}
});

return $app;
