@php
$branding = \App\Services\Config\Config::getBrandingWeb();
@endphp
<!--begin::Aside-->
<div id="kt_aside" class="aside aside-default aside-hoverable" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_toggle">
	<!--begin::Brand-->
	<div class="aside-logo flex-column-auto px-10 pt-7 " id="kt_aside_logo" style="">
		<!--begin::Logo-->
		<a href="{{ route('admin.home') }}" class="branding-logo-sidebar" style="width: 100%; text-align: center;">
			<img alt="{{ config('company.short_name') }}" src="{{ $branding['logo_header']->url() }}" class="logo-default theme-light-show mh-40px"  />
		</a>
		<!--end::Logo-->
	</div>
	<!--end::Brand-->
	<!--begin::Aside menu-->
	<div class="aside-menu flex-column-fluid ps-3 pe-1">
		<!--begin::Aside Menu-->
		<!--begin::Menu-->
		<div class="menu menu-sub-indention menu-column menu-rounded menu-title-gray-600 menu-icon-gray-500 menu-active-bg menu-state-primary menu-arrow-gray-500 fw-semibold fs-6 my-5 mt-lg-2 mb-lg-0" id="kt_aside_menu" data-kt-menu="true">
			<div class="hover-scroll-y mx-4" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto" data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="20px" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer">
				@include('partials.menus.home')

				@include('partials.menus.units')

				@include('partials.menus.membresias')

				@include('partials.menus.administracion')

				@include('partials.menus.planes')

				@include('partials.menus.empresas')

				@include('partials.menus.configuracion')
			</div>
		</div>
			<!--end::Menu-->
	</div>
		<!--end::Aside menu-->

</div>
<!--end::Aside-->
