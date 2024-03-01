@extends('layouts.app')
@inject('agent', 'Phattarachai\LaravelMobileDetect\Agent')

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


@if (count($tagList) > 0)
	  	<div class="systems">
	    @foreach ($tagList as $tag)
		    <a class="tag" href="{{
		    	route('system.index', [
		    		'tag' => $tag['key'],
		    		'system' => $system
		    	])
		    }}">
		    {{ $tag['key'] }}
			 <span class="tag-count">({{ $tag['doc_count'] }})</span></a>
		@endforeach
		</div>
	@endif

<div class="row">
    @foreach ($docs as $doc)
      <div class="col-sm-6 col-md-4 mb-3 text-center libris-item">
            <a href="{{ $doc['download'] }}" target="_blank">
              <img src="{{ $doc['thumbnail'] }}" class="book-cover">
            <br/>
            <div class="doc-title">{{ $doc['name'] ? $doc['name'] : '[empty]' }}</a></div>
            @foreach ($doc['tags'] as $tag)
            <a class="badge badge-secondary" href="{{ route("system.index", ['system' => $system ])}}?tag={{ $tag }}">{{ urldecode($tag) }}</a>
            @endforeach
            <div class="btn-group" role="group" aria-label="Basic example">
              @if (App::environment(['local', 'staging']))
                <a class="btn btn-light" role="button" href="/debug/thumbnail?id={{ urlencode($doc['id'])}}" title="Thumbnail debug"><i class="bi bi-image"></i></a>
              @endif
              <a class="btn btn-light" role="button" target="_blank" href="{{ route('document.iframe', [ 'file' => urlencode($doc['path']) ] ) }}" title="View in frame"><i class="bi bi-file-earmark-pdf"></i></a>
              <a class="btn btn-light" role="button" target="_blank" href="{{ $doc['download'] }}" title="View in browser"><i class="bi bi-file-earmark-pdf-fill"></i></a>
              <!-- <a class="btn btn-light" role="button" target="_blank" href="{{ route('document.iframe', [ 'file' => urlencode($doc['path']) ] ) }}" title="Download"><i class="bi bi-file-earmark-arrow-down-fill"></i></a> -->
            </div>
      </div>
	@endforeach
	</ul>

</div>

	Page {{ $page }} of {{ $pages }} 


  {!!  $pagination->links() !!}
  </div>
@endsection