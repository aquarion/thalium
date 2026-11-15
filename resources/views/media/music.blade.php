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

    @include('partials.system-tabs', ['system' => $system, 'active' => 'music', 'counts' => $counts])
    @include('media._tags', ['route' => 'system.music'])

    @if ($items->count() > 0)
      <table class="table table-sm table-hover track-list">
        <tbody>
          @foreach ($items as $item)
            <tr>
              <td class="track-play">
                <button type="button" class="btn btn-sm btn-light" data-track-url="{{ $item->url() }}" data-track-title="{{ $item->title }}" aria-label="Play {{ $item->title }}"><i class="bi bi-play-fill"></i></button>
              </td>
              <td>
                {{ $item->title }}
                @if ($item->artist)<span class="text-muted"> &mdash; {{ $item->artist }}</span>@endif
                @foreach ($item->tags as $itemTag)
                  <a class="badge badge-secondary" href="{{ route('system.music', ['system' => $system, 'tag' => $itemTag]) }}" data-section-link>{{ $itemTag }}</a>
                @endforeach
              </td>
              <td class="text-end">{{ $item->durationLabel() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <p class="text-muted">No music in this section yet.</p>
    @endif

    {{ $items->links() }}
  </div>
@endsection
