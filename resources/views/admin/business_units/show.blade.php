@extends('layouts.craft')

@section('title', $unit->name)


@section('content')
	<admin-business-units-show unit-id="{{ (int) $unit->id }}"></admin-business-units-show>
@endsection
