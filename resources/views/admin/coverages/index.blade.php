@extends('layouts.craft')

@section('title', 'Catálogo de coberturas')

@section('content')
    <admin-coverages-index
        :initial-categories='@json($initialCategories)'
        :initial-units='@json($initialUnits)'
    ></admin-coverages-index>
@endsection
