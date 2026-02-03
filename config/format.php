<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración de formatos por locale
    |--------------------------------------------------------------------------
    |
    | Aquí definimos las preferencias de formato para cada idioma soportado:
    | - number_locale: se usa en Intl.NumberFormat en el frontend (Vue)
    | - date_format:   formato de fecha para PHP (ej: d/m/Y)
    | - time_format:   formato de hora para PHP (ej: H:i, h:i A)
    | - datetime_format: combinación fecha+hora para PHP
    | - js_date_format: formato compatible con datepickers JS (flatpickr, etc.)
    |
    */

    'locales' => [

        'es' => [
            'number_locale'   => 'es-ES',
            'date_format'     => 'd/m/Y',
            'time_format'     => 'H:i',
            'datetime_format' => 'd/m/Y H:i',
            'js_date_format'  => 'dd/MM/yyyy',
        ],

        'en' => [
            'number_locale'   => 'en-US',
            'date_format'     => 'm/d/Y',
            'time_format'     => 'h:i A',
            'datetime_format' => 'm/d/Y h:i A',
            'js_date_format'  => 'MM/dd/yyyy',
        ],

    ],

];
