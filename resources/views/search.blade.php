@extends('layouts.app')

@section('title', 'Systems Index')


@section('content')
  <div class="starter-template" >
    <h1>Search Results for
    	<q>{{ $query }}</q>
        @if ($system)
            in <a href="{{ route("system.index", ['system' => $system])}}">{{ $system }}</a>
        @endif

        @if (count($systems) == 1 && !$system)
            @php
                $system = $systems[0]['key'];
            @endphp
            <h2>Only found in <a href="{{ route("system.index", ['system' => $system])}}">{{ $system }}</a></h2>
        @endif
    </h1>
	@if ($system)
	  	<p>[
		    <a href="{{
		    	route('search', [
		    		'q' => $query
		    	])
		    }}">
		    Search all systems</a>]
		</p>
	@endif

  	@if (count($systems) > 1)
	  	<div class="systems">
	  	Systems:
	    @foreach ($systems as $sys)
		    <a class="tag" href="{{
		    	route('search', [
		    		'q' => $query,
		    		's' => $sys['key']
		    	])
		    }}">
		    {{ $sys['key'] }}
			 <span class="tag-count">({{ $sys['doc_count'] }})</span></a>
		@endforeach
		</div>
	@endif

	@if (($system) and ($tagList) and count($tagList) > 1)
	  	<div class="tags">
	  	Tags:
	    @foreach ($tagList as $i_tag)
		    <a class="btn btn-light @if($active_tag == $i_tag['key']) active @endif" href="{{
		    	route('search', [
		    		'q' => $query,
		    		's' => $system,
		    		't' => $i_tag['key']
		    	])
		    }}">
		    {{ $i_tag['key'] }}
			 <span class="tag-count">({{ $i_tag['doc_count'] }})</span></a>
		@endforeach
		</div>
	@endif

<!--
    <ul>
    @foreach ($top_docs as $doc)
	    <li>{{ $doc['key'] }} ({{ $doc['doc_count'] }})</li>
	@endforeach
	</ul>
 -->

    <dl>
    @foreach ($hits as $page)

        <dt>
            <b>{{ $page['_source']['system'] }}</b> &ndash;
            <a href="{{ route('document.iframe', [ 'file' => urlencode($page['_source']['path']), 'page' => $page['_source']['pageNo'] ] ) }}">
        	{{ $page['_source']['title'] }} &ndash; p{{ $page['_source']['pageNo'] }}
        </a></dt>
        	@foreach ($page['highlight']['attachment.content'] as $highlight)
        		<dd>{!! $highlight !!}</dd>
        	@endforeach
	@endforeach
	</dl>

	{!! $pagination->render(); !!}

  </div>
@endsection