{{-- /resources/views/admin/templates/index.blade.php --}}
@extends('layouts.craft')

@section('content')
	<div class="container-fluid">
		<admin-templates-index
			:initial-templates='@json($templates)'
		/>
	</div>
@endsection
