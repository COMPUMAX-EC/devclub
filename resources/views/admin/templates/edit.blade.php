{{-- /resources/views/admin/templates/edit.blade.php --}}
@extends('layouts.craft')

@section('title', $template->name)

@section('content')
	<div class="container-fluid">
		<admin-templates-edit
			:template-id='@json($template->id)'
		/>
	</div>
@endsection
