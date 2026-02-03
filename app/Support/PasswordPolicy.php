<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordPolicy
{
    public function rule(array $context = []): array
    {
        $cfg = config('password_policy');

        $rule = PasswordRule::min((int)$cfg['min']);

        // Si se exige cualquier tipo de letra (o mayúsculas/minúsculas/mixed), activa letters()
        if (!empty($cfg['require']['letters'])
            || !empty($cfg['require']['uppercase'])
            || !empty($cfg['require']['lowercase'])
            || !empty($cfg['require']['mixed_case'])) {
            $rule->letters();
        }

        // mixedCase (propio del builder)
        if (!empty($cfg['require']['mixed_case'])) {
            $rule->mixedCase();
        }

        if (!empty($cfg['require']['numbers'])) {
            $rule->numbers();
        }

        if (!empty($cfg['require']['symbols'])) {
            $rule->symbols();
        }

        if (!empty($cfg['uncompromised']['enabled'])) {
            $rule->uncompromised((int)($cfg['uncompromised']['threshold'] ?? 1));
        }

        // Validaciones adicionales no cubiertas por el builder
        $extra = function (string $attribute, $value, $fail) use ($cfg, $context) {
            $pwd = (string)$value;
            $pwdLower = Str::lower($pwd);

            // Máximo (PasswordRule no valida max)
            $max = (int)($cfg['max'] ?? 0);
            if ($max > 0 && Str::length($pwd) > $max) {
                return $fail(__('La contraseña no debe exceder :max caracteres.', ['max' => $max]));
            }

            // Mayúsculas / minúsculas individuales (usar closure porque no existen uppercase()/lowercase())
            if (!empty($cfg['require']['uppercase']) && !preg_match('/[A-Z]/', $pwd)) {
                return $fail(__('Debe incluir al menos una mayúscula.'));
            }
            if (!empty($cfg['require']['lowercase']) && !preg_match('/[a-z]/', $pwd)) {
                return $fail(__('Debe incluir al menos una minúscula.'));
            }

            // Palabras prohibidas
            foreach ((array)($cfg['banned'] ?? []) as $bad) {
                $bad = Str::lower((string)$bad);
                if ($bad !== '' && Str::contains($pwdLower, $bad)) {
                    return $fail(__('La contraseña contiene patrones inseguros.'));
                }
            }

            // Partes del usuario a prohibir
            $map = [
                'first_name'   => $context['first_name']   ?? null,
                'last_name'    => $context['last_name']    ?? null,
                'display_name' => $context['display_name'] ?? null,
                'email_local'  => isset($context['email']) ? Str::before($context['email'], '@') : null,
            ];
            foreach ((array)config('password_policy.forbid_user_parts', []) as $key) {
                $candidate = Str::lower((string)($map[$key] ?? ''));
                if ($candidate !== '' && Str::contains($pwdLower, $candidate)) {
                    return $fail(__('La contraseña no debe incluir información personal (nombre, email, etc.).'));
                }
            }
        };

        return [$rule, $extra];
    }

    /**
     * Estructura para el frontend (no incluir secretos).
     */
    public function forFrontend(): array
    {
        $cfg = config('password_policy');

        return [
            'min'     => (int) $cfg['min'],
            'max'     => (int) $cfg['max'],
            'require' => [
                'uppercase' => (bool) ($cfg['require']['uppercase'] ?? false),
                'lowercase' => (bool) ($cfg['require']['lowercase'] ?? false),
                'numbers'   => (bool) ($cfg['require']['numbers']   ?? false),
                'symbols'   => (bool) ($cfg['require']['symbols']   ?? false),
                'mixed_case'=> (bool) ($cfg['require']['mixed_case']?? false),
                'letters'   => (bool) ($cfg['require']['letters']   ?? false),
            ],
            'messages'=> [
                'min'        => __('Debe tener al menos :min caracteres.', ['min' => $cfg['min']]),
                'uppercase'  => __('Debe incluir al menos una mayúscula.'),
                'lowercase'  => __('Debe incluir al menos una minúscula.'),
                'numbers'    => __('Debe incluir al menos un número.'),
                'symbols'    => __('Debe incluir al menos un símbolo.'),
                'max'        => __('No debe exceder :max caracteres.', ['max' => $cfg['max']]),
                'noPersonal' => __('No debe incluir tu nombre ni tu email.'),
            ],
        ];
    }
}
