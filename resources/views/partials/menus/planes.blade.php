@if(env_any('MODULE_PRODUCTOS'))
	<!--begin:Menu item-->
	<div class="menu-item pt-5">
		<!--begin:Menu content-->
		<div class="menu-content">
			<span class="fw-bold text-muted text-uppercase fs-7">Productos Retail</span>
		</div>
		<!--end:Menu content-->
	</div>
	<!--end:Menu item-->

	<!--begin:Menu item-->
	<div class="menu-item">
		<!--begin:Menu link-->
		<a class="menu-link" href="{{ route('admin.products.index') }}">
			<span class="menu-icon">
				<i class="ki-duotone ki-notepad-edit fs-2">
					<span class="path1"></span>
					<span class="path2"></span>
				</i>
			</span>
			<span class="menu-title">Editar Productos</span>
		</a>
		<!--end:Menu link-->
	</div>
	<!--end:Menu item-->

	<!--begin:Menu item-->
	<div class="menu-item">
		<!--begin:Menu link-->
		<a class="menu-link" href="{{ route('admin.coverages.index') }}">
			<span class="menu-icon">
				<i class="ki-duotone ki-notepad-edit fs-2">
					<span class="path1"></span>
					<span class="path2"></span>
				</i>
			</span>
			<span class="menu-title">Editar Coberturas</span>
		</a>
		<!--end:Menu link-->
	</div>
	<!--end:Menu item-->
@endif