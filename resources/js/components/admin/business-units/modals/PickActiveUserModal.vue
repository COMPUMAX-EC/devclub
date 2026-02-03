<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Seleccionar usuario activo</h5>
					<button type="button" class="btn-close" @click="hide()"></button>
				</div>

				<div class="modal-body">
					<div class="d-flex gap-2 mb-3">
						<input
							type="text"
							class="form-control"
							placeholder="Buscar (email / nombre)"
							v-model="q"
							@keyup.enter="fetchUsers(1)"
						/>
						<button class="btn btn-light" type="button" @click="fetchUsers(1)">Buscar</button>
					</div>

					<div v-if="loading" class="text-muted">Cargando...</div>

					<div v-else>
						<div v-if="users.length === 0" class="text-muted">Sin resultados.</div>

						<div v-else class="table-responsive">
							<table class="table table-row-dashed align-middle">
								<thead>
									<tr class="text-gray-600 fw-bold">
										<th>Usuario</th>
										<th>Email</th>
										<th class="text-end">Acción</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="u in users" :key="u.id">
										<td>
											<div class="fw-semibold">{{ u.display_name || (u.first_name + ' ' + u.last_name) }}</div>
											<div class="text-muted small">#{{ u.id }}</div>
										</td>
										<td>{{ u.email }}</td>
										<td class="text-end">
											<button class="btn btn-sm btn-primary" type="button" @click="pick(u)">
												Seleccionar
											</button>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="d-flex flex-stack mt-3">
							<div class="text-muted small">Total: {{ pagination.total }}</div>
							<div class="d-flex gap-2">
								<button class="btn btn-sm btn-light" :disabled="pagination.current_page <= 1" @click="fetchUsers(pagination.current_page - 1)">
									Anterior
								</button>
								<button class="btn btn-sm btn-light" :disabled="pagination.current_page >= pagination.last_page" @click="fetchUsers(pagination.current_page + 1)">
									Siguiente
								</button>
							</div>
						</div>

					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-light" @click="hide()">Cerrar</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	data() {
		return {
			modal: null,
			loading: false,
			q: '',
			users: [],
			pagination: {
				current_page: 1,
				last_page: 1,
				per_page: 15,
				total: 0,
			},
		};
	},
	methods: {
		open() {
			this.ensureModal();
			this.q = '';
			this.users = [];
			this.pagination = { current_page: 1, last_page: 1, per_page: 15, total: 0 };
			this.modal.show();
			this.fetchUsers(1);
		},
		hide() {
			this.modal?.hide();
		},
		ensureModal() {
			if (!this.modal) {
				this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl);
			}
		},
		async fetchUsers(page) {
			this.loading = true;
			try {
				const res = await axios.get(this.route('admin.business-units.api.users.active'), {
					params: { q: this.q || undefined, page, per_page: 15 },
				});
				this.users = res.data.data || [];
				this.pagination = res.data.meta?.pagination || this.pagination;
			} catch (e) {
				window.flash(this.humanError(e), 'danger');
			} finally {
				this.loading = false;
			}
		},
		pick(u) {
			this.hide();
			this.$emit('confirm', { user_id: u.id });
		},
		humanError(e) {
			return (
				e?.response?.data?.message ||
				(e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null) ||
				e?.message ||
				'Error'
			);
		},
	},
};
</script>
