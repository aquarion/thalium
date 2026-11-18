@extends('layouts.app')
@section('title', 'Maps - '.$system)

@section('content')
  <div class="starter-template" id="system-section">
    <h1>{{ $system }}</h1>

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Systems</a></li>
        <li class="breadcrumb-item"><a href="{{ route('system.index', ['system' => $system]) }}" data-section-link>{{ $system }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Maps{{ $tag ? ': '.$tag : '' }}</li>
      </ol>
    </nav>

    @include('partials.system-tabs', ['system' => $system, 'active' => 'maps', 'counts' => $counts, 'tag' => $tag])
    @include('media._tags', ['route' => 'system.maps'])

    <div class="row">
      @forelse ($items as $item)
        @include('media._map-card', ['item' => $item, 'badgeRoute' => 'system.maps', 'badgeParams' => ['system' => $system], 'sectionLink' => true])
      @empty
        <p class="text-muted">
          @if ($tag)
            No maps tagged &ldquo;{{ $tag }}&rdquo;.
            <a href="{{ route('system.maps', ['system' => $system]) }}" data-section-link>Show all maps</a>
          @else
            No maps in this section yet.
          @endif
        </p>
      @endforelse
    </div>

    {{ $items->links() }}
  </div>
@endsection
