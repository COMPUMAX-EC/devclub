@extends('layouts.craft')
@section('title','Editar usuario')

@section('actions')
	<a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-light">Volver</a>
@endsection

@section('content')
	@include('admin.users._form', ['user' => $user, 'assignedRoles' => $assignedRoles])
@endsection
