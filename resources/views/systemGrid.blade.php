@extends('layouts.app')

@section('title', 'Systems - '.$system)


@section('content')
  <div class="starter-template" >
    <h1>{{ $system }}</h1>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item" aria-current="page"><a href="{{ route("home") }}">Systems</a></li>
    @if ($tag)
      <li class="breadcrumb-item" aria-current="page"> <a href="{{ route("system.index", ['system' => $system ])}}">{{ $system }} </a></li>
      <li class="breadcrumb-item active" aria-current="page"> Tag: {{ $tag }} </li>
    @else
      <li class="breadcrumb-item active" aria-current="page"> {{ $system }} </li>
    @endif
  </ol>
</nav>

<div class="row">
    @foreach ($docs as $doc)
      <div class="col-sm-6 col-md-4 mb-3 text-center">
        <a href="{{ $doc['download'] }}"><img src="{{ $doc['thumbnail'] }}"><br/>
            {{ $doc['name'] ? $doc['name'] : '[empty]' }}</a>
            @foreach ($doc['tags'] as $tag)
            <a class="badge badge-secondary" href="{{ route("system.index", ['system' => $system ])}}?tag={{ $tag }}">{{ urldecode($tag) }}</a>
            @endforeach
      </div>
	@endforeach
	</ul>

</div>

	Page {{ $page }} of {{ $pages }}


  {!!  $pagination->links() !!}
  </div>
@endsection