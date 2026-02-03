{{-- resources/views/admin/capitated/batches/show.blade.php --}}
@extends('layouts.craft')

@section('title', 'Lote de carga capitado')

@section('content')
	<admin-capitados-batch-show
		:company-id="{{ (int) $company->id }}"
		:batch-id="{{ (int) $batch->id }}"
	/>
@endsection
