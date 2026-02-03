<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VerifyRecaptcha
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->input('recaptcha_token');

        if (!$token) {
            return response()->json(['message' => 'reCAPTCHA no enviado', 'error'], 422);
        }

        try {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret'   => config('services.recaptcha.secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            $data = $response->json();

            if (!($data['success'] ?? false)) {
                // Log para depuración
                \Log::warning('reCAPTCHA fallido', $data);
                return response()->json(['message' => 'reCAPTCHA inválido'], 422);
            }

        } catch (\Throwable $e) {
            \Log::error('Error al validar reCAPTCHA: ' . $e->getMessage());
            return response()->json(['message' => 'Error al validar reCAPTCHA'], 500);
        }

        return $next($request);
    }
}
