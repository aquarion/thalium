{{-- A sortable column header. Needs $key, $label, plus $sort, $dir, $system, $tag from the page. --}}
@php
  $isSorted = $sort === $key;
  $nextDir = ($isSorted && $dir === 'asc') ? 'desc' : 'asc';
  $href = route('system.music', array_filter(
      ['system' => $system, 'tag' => $tag, 'sort' => $key, 'dir' => $nextDir],
      fn ($value) => $value !== null && $value !== ''
  ));
@endphp
<th class="{{ $class ?? '' }}" @if ($isSorted) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
  <a class="text-reset text-decoration-none" href="{{ $href }}" data-section-link>{{ $label }}@if ($isSorted)<i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill ms-1"></i>@endif</a>
</th>
