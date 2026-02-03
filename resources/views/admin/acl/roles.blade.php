@extends('layouts.craft')

@section('title', __('Roles y permisos (:guard)', ['guard' => $guard]))

@section('content')

        <admin-acl-roles-permissions-matrix guard="{{ $guard }}"></admin-acl-roles-permissions-matrix>
@endsection
