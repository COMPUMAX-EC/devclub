{{-- resources/views/admin/users/_table.blade.php --}}
@if($users instanceof \Illuminate\Contracts\Pagination\Paginator ? $users->count() : collect($users)->count())
<div class="table-responsive">
    <table class="table align-middle table-row-dashed">
        <thead>
        <tr class="fw-bold text-muted">
            <th>Nombre</th>
            <th>Email</th>
            <th>Roles</th>
            <th>Estado</th>
            <th>Último login</th>
            <th class="text-end">Acciones</th>
        </tr>
        </thead>
        <tbody class="fs-7">
        @foreach($users as $u)
            <tr>
                <td class="fw-semibold">{{ $u->first_name }} {{ $u->last_name }}</td>
                <td>{{ $u->email }}</td>
                <td>
                    @foreach($u->roles as $r)
                        <span class="badge badge-light-primary me-1">{{ $r->label }}</span>
                    @endforeach
                </td>
                <td>
                    <span class="badge badge-light-{{ $u->status === 'active' ? 'success' : ($u->status === 'locked' ? 'danger' : 'warning') }}">
                        {{ ucfirst($u->status) }}
                    </span>
                </td>
                <td>{{ optional($u->last_login_at)->format('Y-m-d H:i') ?? '—' }}</td>

				<td class="text-end">
					<a href="#" class="btn btn-sm btn-light btn-flex btn-center btn-active-light-primary"
					   data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
						<i class="ki-duotone ki-burger-menu-6"></i>
					</a>

					<!--begin::Menu-->
					<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600
								menu-state-bg-light-primary fw-semibold fs-7 w-175px py-4"
						 data-kt-menu="true">

						@can('view', $u)
						<div class="menu-item px-3">
							<a class="menu-link px-3" href="{{ route('admin.users.show', $u) }}">Ver</a>
						</div>
						@endcan

						@can('update', $u)
						<div class="menu-item px-3">
							<a class="menu-link px-3" href="{{ route('admin.users.edit', $u) }}">Editar</a>
						</div>
						@endcan

						@can('impersonate', $u)
						<div class="menu-item px-3">
							<form method="POST" action="{{ route('admin.users.impersonate', $u) }}"
								  onsubmit="return confirm('¿Impersonar a este usuario?');">
								@csrf
								<button class="btn btn-sm btn-light menu-link px-3" type="submit">Impersonar</button>
							</form>
						</div>
						@endcan

						@can('users.sessions.revoke')
						@can('update', $u)
						<div class="menu-item px-3">
							<a href="#"
							   class="menu-link px-3 text-danger js-revoke-sessions"
							   id="revoke-sessions-{{ $u->id }}"
							   data-user-id="{{ $u->id }}"
							   data-url="{{ route('admin.users.sessions.revoke', $u) }}">
								Revocar sesiones
							</a>
						</div>
						@endcan
						@endcan

						<div class="separator my-2"></div>

						@canany(['restore','delete'], $u)
							@if($u->trashed())
								@can('restore', $u)
								<div class="menu-item px-3">
									<form method="POST" action="{{ route('admin.users.restore', $u) }}"
										  onsubmit="return confirm('¿Restaurar usuario?');">
										@csrf
										<button class="btn btn-sm btn-success menu-link px-3" type="submit">Restaurar</button>
									</form>
								</div>
								@endcan
							@else
								@can('delete', $u)
								<div class="menu-item px-3">
									<form method="POST" action="{{ route('admin.users.destroy', $u) }}"
										  onsubmit="return confirm('¿Eliminar (soft delete) este usuario?');">
										@csrf @method('DELETE')
										<button class="btn btn-sm menu-link px-3" type="submit">Eliminar</button>
									</form>
								</div>
								@endcan
							@endif
						@endcanany
					</div>
					<!--end::Menu-->
				</td>

            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@if($users instanceof \Illuminate\Contracts\Pagination\Paginator)
    <div class="d-flex align-items-center justify-content-between mt-3">
        <div class="ms-auto">
            {{ $users->onEachSide(1)->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endif
@else
<div class="text-center text-muted py-10">
    <i class="ki-duotone ki-search-list fs-1"></i>
    <div class="mt-2">No se encontraron usuarios con los filtros actuales.</div>
</div>
@endif

@once
@push('vendor_entries')
<script>
(() => {
  // Evitar doble binding si la tabla se reinyecta
  if (window.__bindRevokeSessionsBound) return;
  window.__bindRevokeSessionsBound = true;

  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
  }

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-revoke-sessions');
    if (!btn) return;

    e.preventDefault();

    const url = btn.dataset.url;
    const userId = btn.dataset.userId;

    // Feedback visual
    const originalHtml = btn.innerHTML;
    btn.classList.add('disabled');
    btn.innerHTML = 'Revocando…';

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
        },
        body: '' // sin payload
      });

      if (!res.ok) throw new Error('HTTP ' + res.status);

      const data = await res.json().catch(() => ({}));

      if (data && data.ok) {
        window.flash && window.flash(data.message || 'Sesiones revocadas.', 'success');
      } else {
        window.flash && window.flash((data && data.message) || 'No se pudo revocar.', 'warning');
      }
    } catch (err) {
      console.error(err);
      window.flash && window.flash('Error al revocar sesiones.', 'danger');
    } finally {
      btn.classList.remove('disabled');
      btn.innerHTML = originalHtml;
    }
  });
})();
</script>
@endpush
@endonce
