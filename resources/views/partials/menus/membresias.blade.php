@php
use App\Models\BusinessUnit;
use App\Models\BusinessUnitMembership;
@endphp

@if(env_any('MODULE_UNITS'))
	@php
		$user = auth('admin')->user();

		$memberships = BusinessUnitMembership::query()
			->with(['unit'])
			->where('user_id', $user->id)
			->where('status','active')
			->whereHas('unit', function ($q) {
				$q->where('status', BusinessUnit::STATUS_ACTIVE)
				  ->where('type', '<>','freelance');
			})
			->get()
			->sortBy(fn($m) => $m->unit?->name ?? '')
			->values();

		if($memberships->isEmpty())
		{
			return;
		}
	@endphp

	<div class="menu-item pt-5">
		<div class="menu-content">
			<span class="fw-bold text-muted text-uppercase fs-7">Oficinas</span>
		</div>
	</div>

	@foreach ($memberships as $m)
		@php
			$unit = $m->unit;
			if (!$unit) continue;

			$icon = match ($unit->type) {
				BusinessUnit::TYPE_CONSOLIDATOR => 'bi-diagram-3',
				BusinessUnit::TYPE_OFFICE       => 'bi-building',
				BusinessUnit::TYPE_COUNTER      => 'bi-shop',
				BusinessUnit::TYPE_FREELANCE    => 'bi-person-badge',
				default                         => 'bi-diagram-3',
			};

			$title = match ($unit->type) {
				BusinessUnit::TYPE_CONSOLIDATOR => $unit->name,
				BusinessUnit::TYPE_OFFICE       => $unit->name,
				BusinessUnit::TYPE_COUNTER      => $unit->name,
				BusinessUnit::TYPE_FREELANCE    => $user->display_name. " (Freelance)",
				default                         => $unit->name,
			};


			if ($title === '') $title = '#' . $unit->id;
		@endphp

		<div class="menu-item">
			<a class="menu-link" href="{{ route('admin.business-units.show', ['unit' => $unit->id]) }}">
				<span class="menu-icon"><i class="bi {{ $icon }}"></i></span>
				<span class="menu-title">{{ $title }}</span>
			</a>
		</div>
	@endforeach

@endif