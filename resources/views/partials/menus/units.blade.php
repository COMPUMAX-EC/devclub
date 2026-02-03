@if(env_any('MODULE_UNITS'))
	
	<!-- comment -->
	@can('unit.structure.view')
		<div class="menu-item pt-5">
			<div class="menu-content">
				<span class="fw-bold text-muted text-uppercase fs-7">Unidades</span>
			</div>
		</div>

		<div class="menu-item">
			<a class="menu-link" href="{{ route('admin.business-units.consolidators.index') }}">
				<span class="menu-icon"><i class="bi bi-diagram-3"></i></span>
				<span class="menu-title">Consolidadoras</span>
			</a>
		</div>

		<div class="menu-item">
			<a class="menu-link" href="{{ route('admin.business-units.offices.index') }}">
				<span class="menu-icon"><i class="bi bi-building"></i></span>
				<span class="menu-title">Offices</span>
			</a>
		</div>

		<div class="menu-item">
			<a class="menu-link" href="{{ route('admin.business-units.freelancers.index') }}">
				<span class="menu-icon"><i class="bi bi-person-badge"></i></span>
				<span class="menu-title">Freelances</span>
			</a>
		</div>
	@endcan
@endif