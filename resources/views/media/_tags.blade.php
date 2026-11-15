{{-- Tag filter chips. Needs $system, $tag, $tagList (tag => count) and $route (system.art|system.music). --}}
@if (count($tagList) > 0)
  <div class="systems">
    <a class="tag{{ $tag ? '' : ' active' }}" href="{{ route($route, ['system' => $system] + ($sortQuery ?? [])) }}" data-section-link>All</a>
    @foreach ($tagList as $name => $count)
      <a class="tag{{ $tag === $name ? ' active' : '' }}" href="{{ route($route, ['system' => $system, 'tag' => $name] + ($sortQuery ?? [])) }}" data-section-link>
        {{ $name }} <span class="tag-count">({{ $count }})</span></a>
    @endforeach
  </div>
@endif
