@if(env_any('MODULE_USERS','MODULE_REGALIAS'))
	<!--begin:Menu item-->
	<div class="menu-item pt-5">
		<!--begin:Menu content-->
		<div class="menu-content">
			<span class="fw-bold text-muted text-uppercase fs-7">Administración</span>
		</div>
		<!--end:Menu content-->
	</div>
	<!--end:Menu item-->

	@if(env('MODULE_USERS', false))
	<!--begin:Menu item-->
	<div class="menu-item">
		<!--begin:Menu link-->
		<a class="menu-link" href="{{ route('admin.users.index') }}">
			<span class="menu-icon">
				<i class="ki-duotone ki-user fs-2">
					<span class="path1"></span>
					<span class="path2"></span>
				</i>
			</span>
			<span class="menu-title">Usuarios</span>
		</a>
		<!--end:Menu link-->
	</div>
	<!--end:Menu item-->
	@endif


	@if(env('MODULE_REGALIAS', false))
		@canany('regalia.users.read','regalia.users.edit')
		<!--begin:Menu item-->
		<div class="menu-item">
			<!--begin:Menu link-->
			<a class="menu-link" href="{{ route('admin.regalias.index') }}">
				<span class="menu-icon">
					<i class="bi bi-currency-dollar"></i>
				</span>
				<span class="menu-title">Regalias</span>
			</a>
			<!--end:Menu link-->
		</div>
		<!--end:Menu item-->
		@endcanany
	@endif





@endif