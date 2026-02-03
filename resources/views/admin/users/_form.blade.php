@php
    /** @var \App\Models\User $user */
    $isEdit = isset($user) && $user->exists;

    // Para leer permisos del USUARIO OBJETIVO (no del actor):
    // Usamos Spatie directamente para consultar si el usuario objetivo
    // está habilitado para vender regular/capitados.
    $targetCanRegular  = $isEdit ? $user->hasPermissionTo('sales.regular.use', 'admin')   : false;
    $targetCanCap      = $isEdit ? $user->hasPermissionTo('sales.capitados.use', 'admin') : false;

    // Perfil (puede ser null si no existe aún)
    $profile = $isEdit ? ($user->staffProfile ?? null) : null;

    // Helper para value() con preferencia: old() -> modelo -> null
    $val = function(string $key, $fallback = null) use ($user) {
        return old($key, $fallback);
    };
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- ERRORES GLOBALES --}}
    @if ($errors->any())
        <div class="alert alert-danger mb-6">
            <div class="fw-bold mb-2">{{ __('Revisa los errores:') }}</div>
            <ul class="mb-0 ps-4">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-6">

        {{-- IDENTIDAD BÁSICA --}}
        <div class="col-12 col-lg-6">
            <div class="card card-flush">
                <div class="card-header"><h3 class="card-title">{{ __('Identidad') }}</h3></div>
                <div class="card-body">

                    <div class="mb-5">
                        <label class="form-label">{{ __('Nombre') }}</label>
                        <input type="text" name="first_name" class="form-control"
                               value="{{ $val('first_name', $isEdit ? $user->first_name : '') }}">
                    </div>

                    <div class="mb-5">
                        <label class="form-label">{{ __('Apellido') }}</label>
                        <input type="text" name="last_name" class="form-control"
                               value="{{ $val('last_name', $isEdit ? $user->last_name : '') }}">
                    </div>

                    <div class="mb-5">
                        <label class="form-label">{{ __('Nombre para mostrar') }}</label>
                        <input type="text" name="display_name" class="form-control"
                               value="{{ $val('display_name', $isEdit ? $user->display_name : '') }}">
                    </div>

                    <div class="mb-5">
                        <label class="form-label">{{ __('Email (login)') }}</label>
                        <input type="email" name="email" class="form-control"
                               value="{{ $val('email', $isEdit ? $user->email : '') }}"
                               @cannot('users.email.update') readonly @endcannot>
                        @cannot('users.email.update')
                            <div class="form-text">{{ __('No tienes permiso para modificar el email.') }}</div>
                        @endcannot
                    </div>

                    {{-- Estado (solo si el actor puede cambiar status) --}}
                    @can('users.status.update')
                        <div class="mb-5">
                            <label class="form-label">{{ __('Estado') }}</label>
                            <select name="status" class="form-select">
                                @php
                                    $currentStatus = $val('status', $isEdit ? $user->status : 'active');
                                @endphp
                                <option value="active"    @selected($currentStatus === 'active')>{{ __('Activo') }}</option>
                                <option value="suspended" @selected($currentStatus === 'suspended')>{{ __('Suspendido') }}</option>
                                <option value="locked"    @selected($currentStatus === 'locked')>{{ __('Bloqueado') }}</option>
                            </select>
                        </div>
                    @endcan

                </div>
            </div>
        </div>

        {{-- CONTACTO / PERFIL --}}
        <div class="col-12 col-lg-6">
            <div class="card card-flush">
                <div class="card-header"><h3 class="card-title">{{ __('Perfil laboral') }}</h3></div>
                <div class="card-body">

                    <div class="mb-5">
                        <label class="form-label">{{ __('Teléfono laboral') }}</label>
                        <input type="text" name="work_phone" class="form-control"
                               value="{{ $val('work_phone', $profile?->work_phone) }}">
                    </div>

                    <div class="mb-5">
                        <label class="form-label">{{ __('Notas (solo backoffice)') }}</label>
                        <textarea name="notes_admin" rows="4" class="form-control">{{ $val('notes_admin', $profile?->notes_admin) }}</textarea>
                    </div>

                </div>
            </div>
        </div>

		{{-- ======================= ROLES (checkboxes) ======================= --}}
		@php
			// Mantiene checks si hubo validación fallida
			$checkedRoles = old('roles', $assignedRoles ?? []);
		@endphp

		@can('users.roles.assign')
        <div class="col-12 ">
            <div class="card card-flush">
                <div class="card-header"><h3 class="card-title">{{ __('Roles') }}</h3></div>
                <div class="card-body">
					<div class="mb-5">

					  <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-2">
						@foreach($allRoles as $roleName)
						  @php $id = 'role_' . md5($roleName); @endphp
						  <div class="col">
							<div class="form-check">
							  <input
								class="form-check-input"
								type="checkbox"
								name="roles[]"
								id="{{ $id }}"
								value="{{ $roleName }}"
								@checked(in_array($roleName, $checkedRoles))
							  >
							  <label class="form-check-label" for="{{ $id }}">
								{{ $roleName }}
							  </label>
							</div>
						  </div>
						@endforeach
					  </div>

					  @error('roles')
						<div class="text-danger small mt-1">{{ $message }}</div>
					  @enderror
					  @error('roles.*')
						<div class="text-danger small mt-1">{{ $message }}</div>
					  @enderror

					  <div class="form-text">
						Selecciona uno o más roles. Las capacidades se derivan de los roles asignados.
					  </div>
					</div>
				  @else
					{{-- Solo lectura si no tiene capacidad para asignar roles --}}
					<div class="mb-5">
					  <label class="form-label d-block">Roles</label>
					  <div>
						@forelse(($assignedRoles ?? []) as $roleName)
						  <span class="badge bg-secondary me-1 mb-1">{{ $roleName }}</span>
						@empty
						  <span class="text-muted">Sin roles</span>
						@endforelse
					  </div>
					</div>
				  @endcan
				  {{-- ===================== FIN ROLES (checkboxes) ===================== --}}
				</div>
			</div>
		</div>

        {{-- COMISIONES (solo si el USUARIO OBJETIVO está habilitado y el ACTOR puede editar comisiones) --}}

        @can('users.commissions.edit')
            @if ($targetCanRegular)
                <div class="col-12 col-lg-6">
                    <div class="card card-flush">
                        <div class="card-header"><h3 class="card-title">{{ __('Comisión — Ventas Regular') }}</h3></div>
                        <div class="card-body">
                            <div class="mb-5">
                                <label class="form-label">{{ __('Primer año (%)') }}</label>
                                <input type="number" step="0.01" min="0" max="100"
                                       name="commission_regular_first_year_pct" class="form-control"
                                       value="{{ $val('commission_regular_first_year_pct', $profile?->commission_regular_first_year_pct) }}">
                            </div>
                            <div class="mb-5">
                                <label class="form-label">{{ __('Renovaciones (%)') }}</label>
                                <input type="number" step="0.01" min="0" max="100"
                                       name="commission_regular_renewal_pct" class="form-control"
                                       value="{{ $val('commission_regular_renewal_pct', $profile?->commission_regular_renewal_pct) }}">
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($targetCanCap)
                <div class="col-12 col-lg-6">
                    <div class="card card-flush">
                        <div class="card-header"><h3 class="card-title">{{ __('Comisión — Ventas Capitados') }}</h3></div>
                        <div class="card-body">
                            <div class="mb-5">
                                <label class="form-label">{{ __('Comisión fija (%)') }}</label>
                                <input type="number" step="0.01" min="0" max="100"
                                       name="commission_capitados_pct" class="form-control"
                                       value="{{ $val('commission_capitados_pct', $profile?->commission_capitados_pct) }}">
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endcan

    </div>

    <div class="mt-7 d-flex gap-3">
        <button type="submit" class="btn btn-primary">{{ $isEdit ? __('Guardar cambios') : __('Crear usuario') }}</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-light">{{ __('Cancelar') }}</a>

    </div>
</form>
