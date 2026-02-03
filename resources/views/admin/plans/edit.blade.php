{{-- /resources/views/admin/plans/edit.blade.php --}}
@extends('layouts.craft')

@section('title', "Plan {$product->name} → Versión #{$planVersion->id}")

@section('content')
	<admin-plans-edit
		:initial-product='@json($product)'
		:initial-plan-version='@json($planVersion)'
		:initial-coverage-categories='@json($coverageCategories)'
		:product-types='@json($productTypes ?? [])'
	/>
@endsection
