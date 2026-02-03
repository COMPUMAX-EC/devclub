@extends('layouts.craft')
@section('title','Detalle de usuario')

@section('actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-light">Volver</a>
    @can('update', $user)
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary ms-2">Editar</a>
    @endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <div class="row g-6">
            <div class="col-md-6">
                <h5 class="fw-bold mb-3">Datos personales</h5>
                <dl class="row">
                    <dt class="col-sm-4">Nombre</dt>
                    <dd class="col-sm-8">{{ $user->first_name }} {{ $user->last_name }}</dd>

                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8">{{ $user->email }}</dd>

                    <dt class="col-sm-4">Teléfono trabajo</dt>
                    <dd class="col-sm-8">{{ $user->staffProfile->work_phone ?? '—' }}</dd>

                    <dt class="col-sm-4">Estado</dt>
                    <dd class="col-sm-8"><span class="badge badge-light-{{ $user->status === 'active' ? 'success' : ($user->status === 'locked' ? 'danger' : 'warning') }}">{{ ucfirst($user->status) }}</span></dd>
                </dl>
            </div>
            <div class="col-md-6">
                <h5 class="fw-bold mb-3">Roles y comisiones</h5>
                <div class="mb-3">
                    @foreach($user->getRoleNames() as $r)
                        <span class="badge badge-light-primary me-1">{{ $r }}</span>
                    @endforeach
                </div>

                @php
                    $p = optional($user->staffProfile);
                @endphp
                <dl class="row">
                    <dt class="col-sm-6">Reg. % Primer año</dt>
                    <dd class="col-sm-6">{{ number_format((float)$p->commission_regular_first_year_pct, 2) }}%</dd>

                    <dt class="col-sm-6">Reg. % Renovación</dt>
                    <dd class="col-sm-6">{{ number_format((float)$p->commission_regular_renewal_pct, 2) }}%</dd>

                    <dt class="col-sm-6">Capitados %</dt>
                    <dd class="col-sm-6">{{ number_format((float)$p->commission_capitados_pct, 2) }}%</dd>
                </dl>
            </div>
        </div>

        <hr class="my-6">

        <h5 class="fw-bold mb-3">Notas (backoffice)</h5>
        <div class="p-4 bg-light rounded">{{ $p->notes_admin ?? '—' }}</div>

        <hr class="my-6">

        <h5 class="fw-bold mb-3">Auditoría reciente</h5>
        <p class="text-muted">(Integraremos la vista de logs aquí más adelante.)</p>
    </div>
</div>
@endsection
