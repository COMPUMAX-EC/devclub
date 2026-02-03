@extends('emails.layouts.base')

@section('title', 'Bienvenido — Tu cuenta')

@section('content')
  <h1>¡Bienvenido(a) {{ $user->first_name }}!</h1>
  <p>Tu cuenta ha sido creada.</p>

  <p><strong>Usuario:</strong> {{ $user->email }}<br>
     <strong>Contraseña temporal:</strong> <span class="kbd">{{ $tempPassword }}</span></p>

  <p>Al ingresar por primera vez te solicitaremos <strong>actualizar tu contraseña</strong>.</p>

  <p style="margin:18px 0">
    <a href="{{ $loginUrl }}" class="btn">Iniciar sesión</a>
  </p>

  <p class="muted">
    Si el botón no funciona, visita:<br>
    {{ $loginUrl }}
  </p>
@endsection
