{{-- One map. Needs $item, $badgeRoute and $badgeParams (route name and base parameters for
     tag badges); $sectionLink (bool) marks the badges for in-page navigation. --}}
@php($isPdf = \App\Service\Media\MediaKind::isPdf($item->path))
<div class="col-sm-6 col-md-4 col-lg-3 mb-3 text-center libris-item">
  @if ($isPdf)
    <a href="{{ $item->url() }}" target="_blank" rel="noopener">
  @else
    <a href="{{ $item->url() }}" data-lightbox data-title="{{ $item->title }}">
  @endif
    @if ($item->thumbnailUrl())
      <img src="{{ $item->thumbnailUrl() }}" class="book-cover" alt="{{ $item->title }}" loading="lazy">
    @else
      <span class="media-no-thumb">{{ $item->title }}</span>
    @endif
    <div class="doc-title">{{ $item->title }}</div>
  </a>
  @foreach ($item->tags as $itemTag)
    <a class="badge text-bg-secondary text-decoration-none" href="{{ route($badgeRoute, $badgeParams + ['tag' => $itemTag]) }}"{!! $sectionLink ? ' data-section-link' : '' !!}>{{ $itemTag }}</a>
  @endforeach
</div>
