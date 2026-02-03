<?php
return [
    // Bandera global para correos
    'mail' => [
        'async' => env('NOTIFY_MAIL_ASYNC', false),
    ],

    // Bandera específica para reset password: null => hereda 'mail.async'
    'password_reset' => [
        'async' => env('NOTIFY_PASSWORD_RESET_ASYNC', null),
    ],
];
