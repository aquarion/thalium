@extends('layouts.app')
@section('title', 'Maps')

@section('content')
  <div class="starter-template" id="maps-section">
    <h1>Maps</h1>

    @if (count($tagList) > 0)
      <div class="systems">
        <a class="tag{{ $tag ? '' : ' active' }}" href="{{ route('maps.index') }}">All</a>
        @foreach ($tagList as $name => $count)
          <a class="tag{{ $tag === $name ? ' active' : '' }}" href="{{ route('maps.index', ['tag' => $name]) }}">
            {{ $name }} <span class="tag-count">({{ $count }})</span></a>
        @endforeach
      </div>
    @endif

    {{-- Groups start collapsed (there can be thousands of maps) unless filtered or alone. --}}
    @forelse ($groups as $group => $items)
      <details class="mb-3"{{ ($tag || $groups->count() === 1) ? ' open' : '' }}>
        <summary class="h4">{{ $group }} <span class="tag-count">({{ $items->count() }})</span></summary>
        <div class="row mt-2">
          @foreach ($items as $item)
            @include('media._map-card', ['item' => $item, 'badgeRoute' => 'maps.index', 'badgeParams' => [], 'sectionLink' => false])
          @endforeach
        </div>
      </details>
    @empty
      <p class="text-muted">
        @if ($tag)
          No maps tagged &ldquo;{{ $tag }}&rdquo;. <a href="{{ route('maps.index') }}">Show all maps</a>
        @else
          No maps yet.
        @endif
      </p>
    @endforelse
  </div>
@endsection
