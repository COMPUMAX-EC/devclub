@extends('emails.base')

@section('title', 'Bienvenido — Admin')

@section('content')
  <h1>¡Bienvenido(a) {{ $user->first_name }}!</h1>
  <p>Tu cuenta administrativa ha sido creada.</p>

  <p><strong>Usuario:</strong> {{ $user->email }}<br>
     <strong>Contraseña temporal:</strong> <span class="kbd">{{ $tempPassword }}</span></p>

  <p>Por seguridad, se te pedirá <strong>cambiar esta contraseña</strong> al iniciar sesión por primera vez.</p>

  <p style="margin:18px 0">
    <a href="{{ $loginUrl }}" class="btn">Ir al panel de login</a>
  </p>

  <p class="muted">
    Si el botón no funciona, copia y pega esta URL en tu navegador:<br>
    {{ $loginUrl }}
  </p>
@endsection
