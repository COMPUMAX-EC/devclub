<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * POST /locale
     * Cambia el idioma del usuario autenticado y lo guarda en users.locale
     */
    public function update(Request $request)
    {
        $supported = config('app.supported_locales', ['es', 'en']);

        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in($supported)],
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok'      => false,
                'message' => 'No autenticado.',
            ], 401);
        }

        $user->locale = $data['locale'];
        $user->save();

        // Actualizamos también la sesión para que tenga efecto inmediato
        session(['locale' => $user->locale]);

        return response()->json([
            'ok'      => true,
            'locale'  => $user->locale,
        ]);
    }
}
