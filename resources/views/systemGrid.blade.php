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


@if (count($tag_list) > 1)
	  	<div class="systems">
	    @foreach ($tag_list as $tag)
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
      <div class="col-sm-6 col-md-4 mb-3 text-center">
            <a href="{{ route('document.iframe', [ 'file' => urlencode($doc['path']) ] ) }}">
              <img src="{{ $doc['thumbnail'] }}">
            <br/>
            {{ $doc['name'] ? $doc['name'] : '[empty]' }}</a>
            @foreach ($doc['tags'] as $tag)
            <a class="badge badge-secondary" href="{{ route("system.index", ['system' => $system ])}}?tag={{ $tag }}">{{ urldecode($tag) }}</a>
            @endforeach
            @if (App::environment(['local', 'staging']))
              <a href="/debug/thumbnail?id={{ urlencode($doc['id'])}}" title="Thumbnail debug">🖼</a>
            @endif
      </div>
	@endforeach
	</ul>

</div>

	Page {{ $page }} of {{ $pages }}


  {!!  $pagination->links() !!}
  </div>
@endsection