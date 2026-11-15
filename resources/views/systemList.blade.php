@extends('layouts.app')

@section('title', 'Systems - '.$system)


@section('content')
  <div class="starter-template" id="system-section">
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

@include('partials.system-tabs', ['system' => $system, 'active' => 'documents', 'counts' => $counts, 'tag' => $tag])

    <ul>
    @foreach ($docs as $doc)
	    <li><a href="{{ $doc['download'] }}">{{ $doc['name'] ? $doc['name'] : '[empty]' }}</a>
        @foreach ($doc['tags'] as $tag)
          <a class="badge text-bg-secondary text-decoration-none" href="{{ route("system.index", ['system' => $system ])}}?tag={{ $tag }}" data-section-link>{{ urldecode($tag) }}</a>
        @endforeach
      </li>
	@endforeach
	</ul>
	Page {{ $page }} of {{ $pages }}


  {!!  $pagination->links() !!}
  </div>
@endsection
