{{-- resources/views/partials/menus/user-menu.blade.php --}}
@php
    /** @var \App\Models\User|null $authUser */
    $authUser = auth('admin')->user();

    $displayName = $authUser?->displayName() ?? 'Usuario';
    $email       = $authUser?->email ?? '';

    // Locale actual (lo comparte BaseController, pero por si acaso tomamos app()->getLocale() como fallback)
    $currentLocale = $currentLocale ?? app()->getLocale();

    // Config de idiomas disponibles en el menú
    $locales = [
        'en' => [
            'label' => 'English',
            'flag'  => 'united-states.svg',
        ],
        'es' => [
            'label' => 'Español',
            'flag'  => 'spain.svg',
        ],
    ];

    $current = $locales[$currentLocale] ?? $locales['es'];
@endphp

<div class="d-flex align-items-stretch flex-shrink-0">
	<!--begin::User-->
	<div class="d-flex align-items-center ms-2 ms-lg-3" id="kt_header_user_menu_toggle">
		<!--begin::Menu wrapper-->
		<div
			class="cursor-pointer symbol symbol-35px symbol-lg-35px"
			data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
			data-kt-menu-attach="parent"
			data-kt-menu-placement="bottom-end"
		>
			<img alt="Pic" src="{{ url('assets/media/avatars/300-1.jpg') }}" />
		</div>

		<!--begin::User account menu-->
		<div
			class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px"
			data-kt-menu="true"
		>
			<!--begin::Menu item-->
			<div class="menu-item px-3">
				<div class="menu-content d-flex align-items-center px-3">
					<!--begin::Avatar-->
					<div class="symbol symbol-50px me-5">
						<img alt="Avatar" src="{{ url('assets/media/avatars/300-1.jpg') }}" />
					</div>
					<!--end::Avatar-->

					<!--begin::Username-->
					<div class="d-flex flex-column">
						<div class="fw-bold d-flex align-items-center fs-5">
							{{ $displayName }}
						</div>

						@if($email)
							<a href="mailto:{{ $email }}" class="fw-semibold text-muted text-hover-primary fs-7">
								{{ $email }}
							</a>
						@endif
					</div>
					<!--end::Username-->
				</div>
			</div>
			<!--end::Menu item-->

			<div class="separator my-2"></div>

			<div class="menu-item px-5">
				<a href="#" class="menu-link px-5">My Profile</a>
			</div>

			<div class="separator my-2"></div>

			{{-- ===================================== --}}
			{{--           CAMBIO DE IDIOMA           --}}
			{{-- ===================================== --}}
			<div
				class="menu-item px-5"
				data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
				data-kt-menu-placement="left-start"
				data-kt-menu-offset="-15px, 0"
			>
				<a href="#" class="menu-link px-5">
					<span class="menu-title position-relative">
						Language
						<span class="fs-8 rounded bg-light px-3 py-2 position-absolute translate-middle-y top-50 end-0">
							{{ $current['label'] }}
							<img
								class="w-15px h-15px rounded-1 ms-2"
								src="{{ url('assets/media/flags/' . $current['flag']) }}"
								alt="{{ $current['label'] }}"
							/>
						</span>
					</span>
				</a>

				<!--begin::Menu sub-->
				<div class="menu-sub menu-sub-dropdown w-175px py-4">
					@foreach($locales as $localeKey => $cfg)
						@php
							$isActive = ($localeKey === $currentLocale);
						@endphp

						<div class="menu-item px-3">
							<a
								href="#"
								class="menu-link d-flex px-5 js-set-locale {{ $isActive ? 'active' : '' }}"
								data-locale="{{ $localeKey }}"
							>
								<span class="symbol symbol-20px me-4">
									<img
										class="rounded-1"
										src="{{ url('assets/media/flags/' . $cfg['flag']) }}"
										alt="{{ $cfg['label'] }}"
									/>
								</span>
								{{ $cfg['label'] }}
							</a>
						</div>
					@endforeach
				</div>
				<!--end::Menu sub-->
			</div>
			{{-- ========== FIN CAMBIO DE IDIOMA ========== --}}

			<div class="menu-item px-5 my-1">
				<a href="#" class="menu-link px-5">Account Settings</a>
			</div>

			<div class="menu-item px-5">
				<a href="{{ route('admin.logout') }}" class="menu-link px-5">Sign Out</a>
			</div>
		</div>
		<!--end::User account menu-->
		<!--end::Menu wrapper-->
	</div>
	<!--end::User -->
</div>

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function () {
		const links = document.querySelectorAll('.js-set-locale');

		if (!links.length) {
			return;
		}

		links.forEach(link => {
			link.addEventListener('click', function (e) {
				e.preventDefault();

				const locale = this.getAttribute('data-locale');
				if (!locale) {
					return;
				}

				fetch(@json(route('admin.locale.update')), {
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': @json(csrf_token()),
						'Accept': 'application/json',
						'Content-Type': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: JSON.stringify({ locale }),
				})
				.then(response => {
					if (!response.ok) {
						throw new Error('HTTP ' + response.status);
					}
					return response.json().catch(() => ({}));
				})
				.then(() => {
					window.location.reload();
				})
				.catch(error => {
					console.error('Error cambiando idioma:', error);
					alert('No se pudo cambiar el idioma. Inténtalo de nuevo.');
				});
			});
		});
	});
</script>
@endpush
