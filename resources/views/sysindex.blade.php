@extends('layouts.app')

@section('title', 'Systems Index')


@section('content')
  <div class="starter-template" >
    <h1>Systems Index</h1>
    <ul>
    @foreach ($systems as $system => $count)
	    <li><a href="{{ url("/system/{$system}") }}">{{ $system }}</a> ({{$count}})</li>
	@endforeach
	</ul>
  </div>
@endsection