@php
$branding = \App\Services\Config\Config::getBrandingWeb();
@endphp
<!--begin::Header-->
<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
	<!--begin::Container-->
	<div class="container-fluid d-flex align-items-stretch justify-content-between">
		<!--begin::Logo bar-->
		<div class="d-flex align-items-center flex-grow-1 flex-lg-grow-0">
			<!--begin::Aside Toggle-->
			<div class="d-flex align-items-center d-lg-none">
				<div class="btn btn-icon btn-active-color-primary ms-n2 me-1" id="kt_aside_toggle">
					<i class="ki-duotone ki-abstract-14 fs-1">
						<span class="path1"></span>
						<span class="path2"></span>
					</i>
				</div>
			</div>
			<!--end::Aside Toggle-->
			<!--begin::Logo-->
			<a href="{{ route('admin.home') }}" class="d-lg-none branding-logo-sidebar">
				<img alt="{{ config('company.short_name') }}" src="{{ $branding['logo_header']->url() }}" class="mh-40px" />
			</a>
			<!--end::Logo-->
		</div>
		<!--end::Logo bar-->
		<!--begin::Topbar-->
		<div class="d-flex align-items-stretch justify-content-between flex-lg-grow-1">
			<!--begin::Search-->
			<div class="d-flex align-items-stretch me-1">

			</div>
			<!--end::Search-->
			<!--begin::Toolbar wrapper-->
				@include('partials.menus.user-menu')
			<!--end::Toolbar wrapper-->
		</div>
		<!--end::Topbar-->
	</div>
	<!--end::Container-->
</div>
<!--end::Header-->
@if (session()->has('impersonator_id') && session()->has('impersonated_email'))
    <div class="alert alert-warning d-flex align-items-center rounded-0 mb-0" role="alert" style="z-index:1070;">
        <div class="flex-grow-1">
            <strong>Impersonación activa:</strong>
            estás usando la sesión de <code>{{ session('impersonated_email') }}</code>.
        </div>
        <form method="POST" action="{{ route('admin.impersonate.stop') }}" class="ms-3">
            @csrf
            <button type="submit" class="btn btn-sm btn-dark">Salir de impersonación</button>
        </form>
    </div>
@endif