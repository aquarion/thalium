@extends('layouts.app')

@section('title', 'Systems Index')


@section('content')

<div class="starter-template" >
    <h1>Systems</h1>

<div class="row">
    @foreach ($systems as $sys)
      <div class="col-sm-6 col-md-2 lg-2 xl-2 mb-2 text-center">
        <a href="{{ url("/system/{$sys['system']}") }}">
        <img src="{{ $sys['thumbnail'] }}" class="book-cover">
        <br/>
        {{ $sys['system']  }} ({{ $sys['count'] }})
        </a>
      </div>
	@endforeach
</div>

@endsection