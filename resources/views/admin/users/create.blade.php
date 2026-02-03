@extends('layouts.craft')
@section('title','Crear usuario')

@section('actions')
    <a href="{{ route('admin.users.index') }}" class="btn btn-light">Volver</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.users.store') }}" id="userForm" autocomplete="off" class="needs-validation" novalidate>
    @csrf

    @include('admin.users._form', [
        'mode' => 'create',
        'user' => null,
    ])

</form>

@endsection
