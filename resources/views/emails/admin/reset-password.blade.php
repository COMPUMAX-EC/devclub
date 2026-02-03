@component('mail::message')
# Restablecer contraseña (admin)

Hola {{ $user->fullName ?? $user->first_name }},

Recibimos una solicitud para restablecer tu contraseña del **admin**. Haz clic en el botón para continuar.

@component('mail::button', ['url' => $url])
Restablecer contraseña
@endcomponent

Este enlace expirará en {{ $minutes ?? 30 }} minutos. Si no solicitaste el cambio, puedes ignorar este correo.

Saludos,
{{ config('app.name') }}
@endcomponent
