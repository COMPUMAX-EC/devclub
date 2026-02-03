{{-- resources/views/admin/companies/edit.blade.php --}}
@extends('layouts.craft')

@section('title', $company->name.': Editar')

@section('content')
    <admin-companies-edit
        :company-id="{{ (int) $company->id }}"
    ></admin-companies-edit>
@endsection
