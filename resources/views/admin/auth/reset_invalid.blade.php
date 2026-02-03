@extends('admin.public')
@section('title','Link inválido')

@section('content')
<div class="card shadow-sm">
  <div class="card-body p-10 text-center">
    <h3 class="mb-4">No pudimos continuar</h3>
    <div class="alert alert-danger mb-6">
      {{ $message ?? 'El enlace para restablecer la contraseña no es válido o ha expirado.' }}
    </div>

    <p class="mb-6">
      Serás redirigido al inicio de sesión en unos segundos.
    </p>

  </div>
</div>

<script>
  setTimeout(function(){ window.location.href = @json($loginUrl); }, 10000);
</script>
@endsection
