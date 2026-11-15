{{-- Documents | Art | Music nav for a system. Needs $system, $active (documents|art|music), $counts. --}}
@php
  $tabs = [
    'documents' => ['Documents', route('system.index', ['system' => $system])],
    'art' => ['Art', route('system.art', ['system' => $system])],
    'music' => ['Music', route('system.music', ['system' => $system])],
  ];
@endphp
<ul class="nav nav-tabs system-tabs mb-3">
  @foreach ($tabs as $key => [$label, $url])
    @php($count = $counts[$key] ?? 0)
    <li class="nav-item">
      <a class="nav-link{{ $active === $key ? ' active' : '' }}{{ $count === 0 ? ' disabled-tab' : '' }}" href="{{ $url }}" data-section-link>
        {{ $label }} <span class="tag-count">({{ $count }})</span>
      </a>
    </li>
  @endforeach
</ul>
