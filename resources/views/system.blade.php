@extends('layouts.app')

@section('title', 'Systems - '.$system)


@section('content')
  <div class="starter-template" >
    <h1>{{ $system }}</h1>
    <ul>
    @foreach ($docs as $doc)
	    <li><a href="{{ $doc['download'] }}">{{ $doc['name'] }}</a></li>
	@endforeach
	</ul>
	Page {{ $page }} of {{ $pages }}
  </div>
@endsection