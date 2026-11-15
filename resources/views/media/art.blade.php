@extends('layouts.app')
@section('title', 'Art - '.$system)

@section('content')
  <div class="starter-template" id="system-section">
    <h1>{{ $system }}</h1>

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Systems</a></li>
        <li class="breadcrumb-item"><a href="{{ route('system.index', ['system' => $system]) }}" data-section-link>{{ $system }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Art{{ $tag ? ': '.$tag : '' }}</li>
      </ol>
    </nav>

    @include('partials.system-tabs', ['system' => $system, 'active' => 'art', 'counts' => $counts, 'tag' => $tag])
    @include('media._tags', ['route' => 'system.art'])

    <div class="row">
      @forelse ($items as $item)
        <div class="col-sm-6 col-md-4 col-lg-3 mb-3 text-center libris-item">
          <a href="{{ $item->url() }}" data-lightbox data-title="{{ $item->title }}">
            @if ($item->thumbnailUrl())
              <img src="{{ $item->thumbnailUrl() }}" class="book-cover" alt="{{ $item->title }}" loading="lazy">
            @else
              <span class="media-no-thumb">{{ $item->title }}</span>
            @endif
            <div class="doc-title">{{ $item->title }}</div>
          </a>
          @foreach ($item->tags as $itemTag)
            <a class="badge text-bg-secondary" href="{{ route('system.art', ['system' => $system, 'tag' => $itemTag]) }}" data-section-link>{{ $itemTag }}</a>
          @endforeach
        </div>
      @empty
        <p class="text-muted">
          @if ($tag)
            No art tagged &ldquo;{{ $tag }}&rdquo;.
            <a href="{{ route('system.art', ['system' => $system]) }}" data-section-link>Show all art</a>
          @else
            No art in this section yet.
          @endif
        </p>
      @endforelse
    </div>

    {{ $items->links() }}
  </div>
@endsection
