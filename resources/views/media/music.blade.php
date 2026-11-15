@extends('layouts.app')
@section('title', 'Music - '.$system)

@section('content')
  <div class="starter-template" id="system-section">
    <h1>{{ $system }}</h1>

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Systems</a></li>
        <li class="breadcrumb-item"><a href="{{ route('system.index', ['system' => $system]) }}" data-section-link>{{ $system }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Music{{ $tag ? ': '.$tag : '' }}</li>
      </ol>
    </nav>

    @include('partials.system-tabs', ['system' => $system, 'active' => 'music', 'counts' => $counts, 'tag' => $tag])
    @include('media._tags', ['route' => 'system.music', 'sortQuery' => $sortQuery])

    @if ($items->count() > 0)
      @php
        $hasArtist = collect($items->items())->contains(fn ($track) => filled($track->artist));
        $hasAlbum = collect($items->items())->contains(fn ($track) => filled($track->album));
        $hasTrack = collect($items->items())->contains(fn ($track) => filled($track->track_number));
      @endphp
      <table class="table table-sm table-hover track-list">
        <thead>
          <tr class="text-muted small">
            <th></th>
            @if ($hasTrack)@include('media._sort-header', ['key' => 'track', 'label' => '#', 'class' => 'text-end'])@endif
            @include('media._sort-header', ['key' => 'title', 'label' => 'Title'])
            @if ($hasArtist)@include('media._sort-header', ['key' => 'artist', 'label' => 'Artist'])@endif
            @if ($hasAlbum)@include('media._sort-header', ['key' => 'album', 'label' => 'Album', 'class' => 'd-none d-md-table-cell'])@endif
            @include('media._sort-header', ['key' => 'length', 'label' => 'Length', 'class' => 'text-end'])
          </tr>
        </thead>
        <tbody>
          @foreach ($items as $item)
            <tr>
              <td class="track-play">
                <button type="button" class="btn btn-sm btn-light" data-track-url="{{ $item->url() }}" data-track-title="{{ $item->artist ? $item->artist.' – '.$item->title : $item->title }}" aria-label="Play {{ $item->title }}"><i class="bi bi-play-fill"></i></button>
              </td>
              @if ($hasTrack)<td class="text-muted text-end">{{ $item->track_number }}</td>@endif
              <td>
                {{ $item->title }}
                @foreach ($item->tags as $itemTag)
                  <a class="badge text-bg-secondary text-decoration-none" href="{{ route('system.music', ['system' => $system, 'tag' => $itemTag] + $sortQuery) }}" data-section-link>{{ $itemTag }}</a>
                @endforeach
              </td>
              @if ($hasArtist)<td class="text-muted">{{ $item->artist }}</td>@endif
              @if ($hasAlbum)<td class="text-muted d-none d-md-table-cell">{{ $item->album }}</td>@endif
              <td class="text-end">{{ $item->durationLabel() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="text-muted">
        @if ($tag)
          No music tagged &ldquo;{{ $tag }}&rdquo;.
          <a href="{{ route('system.music', ['system' => $system]) }}" data-section-link>Show all music</a>
        @else
          No music in this section yet.
        @endif
      </p>
    @endif

    {{ $items->links() }}
  </div>
@endsection
