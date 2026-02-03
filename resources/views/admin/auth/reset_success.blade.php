@extends('admin.public')
@section('title','Contraseña actualizada')

@section('content')
<div class="card shadow-sm">
	<div class="card-body p-10 text-center">
		<h3 class="mb-4">¡Contraseña actualizada!</h3>
		@if(!empty($status))
		<div class="alert alert-success">{{ $status }}</div>
		@else
		<div class="alert alert-success">Tu contraseña fue cambiada correctamente.</div>
		@endif

		<p class="mb-6">Serás redirigido al inicio de sesión en unos segundos.</p>
		<a href="{{ $loginUrl }}" class="btn btn-primary">Ir al login ahora</a>
	</div>
</div>

<script>
	setTimeout(function(){ window.location.href = @json($loginUrl); }, 4000);
</script>
@endsection
