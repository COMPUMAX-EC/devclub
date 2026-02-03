<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as IlluminateRoutingController;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Carbon\Carbon;

abstract class Controller extends IlluminateRoutingController
{
    use AuthorizesRequests, ValidatesRequests;

    public function __construct()
    {
        // Este middleware corre para TODAS las acciones que hereden de este Controller
        $this->middleware(function ($request, $next) {

            // 1) ¿Hay idioma forzado por query (ej: widgets embebidos)?
            $forcedLocale = $this->normalizeLocale($request->query('forced_lang'));

            if ($forcedLocale) {
                $locale  = $forcedLocale;
                $forced  = true;   // no persistimos en sesión ni tocamos users.locale
            } else {
                $locale  = $this->resolveLocale($request);
                $forced  = false;
            }

            App::setLocale($locale);       // traducciones de Laravel
            Carbon::setLocale($locale);    // textos “humanos” de Carbon (opcional)

            // Compartir en vistas (útil para header/switcher)
            view()->share('currentLocale', $locale);

            // Persistir en sesión para próximas requests SOLO si no es forced_lang
            if (! $forced) {
                session(['locale' => $locale]);
            }

            return $next($request);
        });
    }

    /**
     * Normaliza un valor de locale y lo valida contra app.supported_locales.
     */
    protected function normalizeLocale(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        // Limpia caracteres raros
        $value = (string) preg_replace('/[^a-z_-]/i', '', $value);

        $supported = config('app.supported_locales', ['es', 'en']);

        return in_array($value, $supported, true) ? $value : null;
    }

    /**
     * Resuelve el locale “normal” (sin forced_lang).
     *
     * Orden de precedencia:
     * 1) prefijo de ruta /{locale}
     * 2) query ?lang=xx
     * 3) preferencia del usuario autenticado (users.locale)
     * 4) lo que haya en sesión
     * 5) locale por defecto de la app
     */
    protected function resolveLocale($request): string
    {
        $wanted =
            $request->route('locale')
            // ?? $request->query('lang')
            ?? optional($request->user())->locale
            ?? session('locale')
            ?? config('app.locale');

        $locale = $this->normalizeLocale($wanted);

        return $locale ?? config('app.locale');
    }
	
	
	/* =============================================================
	 *                           Toast helpers
	 * ============================================================= */

	protected function jsonToastSuccess(array $payload, string $message, ?string $field = null, int $status = 200)
	{
		return response()->json(array_merge($payload, [
			'message' => $message,
			'toast' => [
				'type' => 'success',
				'message' => $message,
				'field' => $field,
			],
		]), $status);
	}

	protected function jsonToastError(string $message, int $status = 422, ?string $field = null)
	{
		return response()->json([
			'message' => $message,
			'toast' => [
				'type' => 'danger',
				'message' => $message,
				'field' => $field,
			],
		], $status);
	}
}
