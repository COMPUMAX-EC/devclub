@extends('layouts.craft')

@section('content')
	<div id="app-config">
		<admin-config-index
			:initial-categories='@json($categories)'
			:initial-permissions='@json($permissions)'
		></admin-config-index>
	</div>
@endsection
