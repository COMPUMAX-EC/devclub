@extends('layouts.craft')

@section('title', 'Países')

@section('content')
	<admin-countries-index
		:initial-continents='@json($continents)'
	></admin-countries-index>
@endsection
