@extends('layouts.craft')
@section('title','Usuarios')

@php
    // Variables esperadas del controlador:
    // $users (LengthAwarePaginator de App\Models\User)
    // $filters = ['q' => ?, 'role' => ?, 'status' => ?, 'per_page' => ?]
    // $availableRoles = ['superadmin','admin','administrativo','vendedor_regular','vendedor_capitados']
    // $statuses = ['active' => 'Activo', 'suspended' => 'Suspendido', 'locked' => 'Bloqueado']
@endphp

@section('actions')
    @can('create', App\Models\User::class)
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="ki-duotone ki-plus"></i> Nuevo usuario
    </a>
    @endcan
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        @include('admin.users._filters', [
            'filters' => $filters ?? [],
            'availableRoles' => $availableRoles ?? ['admin','administrativo','vendedor_regular','vendedor_capitados','superadmin'],
            'statuses' => $statuses ?? ['active'=>'Activo','suspended'=>'Suspendido','locked'=>'Bloqueado'],
        ])

        @include('admin.users._table', [
            'users' => $users ?? collect(),
        ])
    </div>
</div>

@include('admin.users._modals')
@endsection
