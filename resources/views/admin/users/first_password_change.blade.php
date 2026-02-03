@extends('layouts.craft')

@section('title','Cambiar contraseña')

@section('content')
<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h4 class="mb-3">Actualiza tu contraseña</h4>
        <p class="text-muted">Debes cambiar la contraseña temporal para continuar.</p>

        <form method="POST" action="{{ route('customer.password.first.update') }}">
          @csrf

          <div class="mb-3">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Confirmación</label>
            <input type="password" name="password_confirmation" class="form-control" required>
          </div>

          <button class="btn btn-primary">Guardar y continuar</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
