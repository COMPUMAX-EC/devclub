<!-- /resources/views/admin/products/index.blade.php -->
@extends('layouts.craft')

@section('title', 'Productos')

@section('content')
	<admin-products-index
		:initial-products='@json($products)'
		:initial-product-types='@json($productTypes)'
		:edit-route-map='@json($editRouteMap)'
	></admin-products-index>
@endsection
