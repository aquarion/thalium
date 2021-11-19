@extends('layouts.app')

@section('title', 'Systems Index')


@section('content')
  <div class="starter-template" >
    <h1>Systems Index</h1>
    <ul>
    @foreach ($systems as $system => $count)
    @if ($system)
	    <li><a href="{{ url("/system/{$system}") }}">{{ $system  }}</a> ({{$count}})</li>
    @else
	    <li><a href="{{ url("/system/null") }}">[empty]</a> ({{$count}})</li>
    @endif
	@endforeach
	</ul>
  </div>
@endsection