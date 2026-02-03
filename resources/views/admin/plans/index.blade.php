@extends('layouts.craft')

@section('title', 'Plan ' . $product->name)

@section('content')
	<admin-plans-index
	  :initial-product='@json($product)'
	  :initial-versions='@json($planVersions)'
	/>
@endsection
