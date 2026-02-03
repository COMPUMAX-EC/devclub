<?php

return [
    // Longitud
    'min' => env('PASSWORD_MIN', 8),
    'max' => env('PASSWORD_MAX', 128),

    // Complejidad
    'require' => [
        'uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'numbers'   => env('PASSWORD_REQUIRE_NUMBERS',   true),
        'symbols'   => env('PASSWORD_REQUIRE_SYMBOLS',   true),
        // Si usas 'mixed_case', normalmente implica uppercase + lowercase
        'mixed_case'=> env('PASSWORD_REQUIRE_MIXED_CASE', true),
    ],

    'history' => [
        'enabled'         => true,
        'remember_last'   => 5,     // No permitir reusar dentro de las últimas N
        'retention_days'  => 365,   // Purga opcional de entradas muy viejas
    ],
	
    // No permitir que contenga datos del usuario (insensible a mayúsculas)
    // Campos aceptados: first_name, last_name, display_name, email_local
    'forbid_user_parts' => [
        'first_name', 'last_name', 'display_name', 'email_local',
    ],

    // Palabras o patrones prohibidos (insensible a mayúsculas)
    'banned' => [
        'password', '123456', 'qwerty', 'letmein', 'admin',
    ],

    // Comprobación contra contraseñas filtradas (HIBP).
    // Laravel Password::uncompromised() hace consulta k-anonymity a haveibeenpwned
    'uncompromised' => [
        'enabled'  => env('PASSWORD_UNCOMPROMISED_ENABLED', false),
        'threshold'=> env('PASSWORD_UNCOMPROMISED_THRESHOLD', 1), // nº de apariciones
    ],
];
