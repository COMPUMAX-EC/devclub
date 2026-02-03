@extends('layouts.craft')

@section('title', 'Zonas')

@section('content')
    <div class="row">
        <div class="col-12">
            <admin-zones-index
                :initial-continents='@json($continents)'
            ></admin-zones-index>
        </div>
    </div>
@endsection
