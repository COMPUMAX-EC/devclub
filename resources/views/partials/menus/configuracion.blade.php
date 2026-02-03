@if(env_any('MODULE_COUNTRY','MODULE_COUNTRY_ZONES','MODULE_CONFIG','MODULE_UNITS','MODULE_ROLES','MODULE_PLANTILLAS'))
	
	<!--begin:Menu item-->
	<div class="menu-item pt-5">
		<!--begin:Menu content-->
		<div class="menu-content">
			<span class="fw-bold text-muted text-uppercase fs-7">Configuración</span>
		</div>
		<!--end:Menu content-->
	</div>
	<!--end:Menu item-->

	@if(env('MODULE_COUNTRY'))
		<!--begin:Menu item-->
		<div class="menu-item">
			<!--begin:Menu link-->
			<a class="menu-link" href="{{ route('admin.countries.index') }}">
				<span class="menu-icon">
					<i class="ki-duotone ki-notepad-edit fs-2">
						<span class="path1"></span>
						<span class="path2"></span>
					</i>
				</span>
				<span class="menu-title">Países</span>
			</a>
			<!--end:Menu link-->
		</div>
		<!--end:Menu item-->
	@endif

	@if(env('MODULE_COUNTRY_ZONES'))
		<!--begin:Menu item-->
		<div class="menu-item">
			<!--begin:Menu link-->
			<a class="menu-link" href="{{ route('admin.zones.index') }}">
				<span class="menu-icon">
					<i class="ki-duotone ki-notepad-edit fs-2">
						<span class="path1"></span>
						<span class="path2"></span>
					</i>
				</span>
				<span class="menu-title">Zonas</span>
			</a>
			<!--end:Menu link-->
		</div>
		<!--end:Menu item-->
	@endif

	@if(env('MODULE_CONFIG'))
		<!--begin:Menu item-->
		<div class="menu-item">
			<!--begin:Menu link-->
			<a class="menu-link" href="{{ route('admin.config.index') }}">
				<span class="menu-icon">
					<i class="ki-duotone ki-notepad-edit fs-2">
						<span class="path1"></span>
						<span class="path2"></span>
					</i>
				</span>
				<span class="menu-title">Config Global</span>
			</a>
			<!--end:Menu link-->
		</div>
		<!--end:Menu item-->
	@endif

	@if(env('MODULE_COUNTRY'))
		<!--begin:Menu item-->
		<div class="menu-item">
			<!--begin:Menu link-->
			<a class="menu-link" href="{{ route('admin.templates.index') }}">
				<span class="menu-icon">
					<i class="ki-duotone ki-notepad-edit fs-2">
						<span class="path1"></span>
						<span class="path2"></span>
					</i>
				</span>
				<span class="menu-title">Plantillas</span>
			</a>
			<!--end:Menu link-->
		</div>
		<!--end:Menu item-->
	@endif
		
	@if(env('MODULE_ROLES'))
		@can('system.roles')
			@php
			$guards = array_diff(array_keys(config('auth.guards')), ["web"]);
			@endphp

			@foreach($guards as $guard)
				<!--begin:Menu item-->
				<div class="menu-item">
					<!--begin:Menu link-->
					<a class="menu-link" href="{{ route('admin.acl.roles-permissions.index', $guard) }}">
						<span class="menu-icon">
							<i class="ki-duotone ki-notepad-edit fs-2">
								<span class="path1"></span>
								<span class="path2"></span>
							</i>
						</span>
						<span class="menu-title">Roles {{ ucfirst($guard) }}</span>
					</a>
					<!--end:Menu link-->
				</div>
				<!--end:Menu item-->
			@endforeach
		@endcan
	@endif

	@if(env('MODULE_UNITS'))
		@can("debug.unit.permission")
		<!--begin:Menu item-->
		<div class="menu-item">
			<!--begin:Menu link-->
			<a class="menu-link" href="{{ route('admin.debug.user-units') }}">
				<span class="menu-icon">
					<i class="ki-duotone ki-notepad-edit fs-2">
						<span class="path1"></span>
						<span class="path2"></span>
					</i>
				</span>
				<span class="menu-title">Debug Permisos Unidades</span>
			</a>
			<!--end:Menu link-->
		</div>
		<!--end:Menu item-->
		@endcan
	@endif
@endif