@extends('layouts.app')

@section('title', 'Systems Index')


@section('content')
  <div class="starter-template" >
    <h1>Search Results for 
    	<q>{{ $query }}</q>
    	@if ($system)
    		in <a href="{{ route("system.index", ['system' => $system])}}">{{ $system }}</a>
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

        <dt><a href="{{ $page['_source']['download'] }}#page={{ $page['_source']['pageNo'] }}">
        	{{ $page['_source']['title'] }} p{{ $page['_source']['pageNo'] }}
        </a></dt>
        <dd>
        	@foreach ($page['highlight']['attachment.content'] as $highlight)
        		<p>{!! $highlight !!}</p>
        	@endforeach

        </dd>
	@endforeach
	</dl>

	{!! $pagination->render(); !!}

  </div>
@endsection