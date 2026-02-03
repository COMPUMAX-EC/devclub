@extends('layouts.craft')

@section('title', 'Demo Select2')

@section('content')
	<div class="card">
		<div class="card-header">
			<h3 class="card-title">Demo Select2 con países</h3>
	</div>
		<div class="card-body">
		<dev-select2-country-demo
			:initial-countries='@json($countries)'
			:countries-iso3-map='@json($countriesIso3Map)'
			></dev-select2-country-demo>
	</div>
		</div>
	@endsection
