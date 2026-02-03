@component('mail::message')
# Restablecer contraseña

Hola {{ $user->fullName ?? $user->first_name }},

Para restablecer tu contraseña, utiliza el siguiente botón.

@component('mail::button', ['url' => $url])
Restablecer contraseña
@endcomponent

Este enlace expira en {{ $minutes ?? 30 }} minutos. Si no solicitaste este cambio, ignora este correo.

Saludos,
{{ config('app.name') }}
@endcomponent
