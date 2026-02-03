@php 
	$items = \App\Support\Breadcrumbs::getAll();
@endphp

@if (count($items)>1)
  <ul class="breadcrumb fw-semibold fs-base my-1" >
  @foreach ($items as $item)
	@php 
		$isLast = $loop->last;
	@endphp
	<li class="breadcrumb-item text-muted" {{ $isLast ? 'aria-current=page' : '' }}>
		@if (!empty($item['url']))
			<a href="{{ $item['url'] }}" class="{{ $isLast ? 'text-gray-800':'text-muted' }}" >
				{{ $item['title'] }}
			</a>
		@else
        <span class="{{ $isLast ? 'text-gray-800':'text-muted' }}">{{ $item['title'] }}</span>
		@endif
    </li>
  @endforeach
</ul>
@endif
