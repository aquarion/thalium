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
  
  	@if (count($systems) > 1)
	  	<div class="systems">
	  	Systems:
	    @foreach ($systems as $system)
		    <a class="tag" href="{{ 
		    	route('search', [
		    		'q' => $query,
		    		's' => $system['key']
		    	])
		    }}">
		    {{ $system['key'] }} 
			 <span class="tag-count">{{ $system['doc_count'] }}</span></a>
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
            <a href="{{ $page['_source']['download'] }}#page={{ $page['_source']['pageNo'] }}">
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