<!-- resources/js/components/admin/business-units/modals/GsaCommissionsModal.vue -->
<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-xl modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Regalías</h5>
					<button type="button" class="btn-close" @click="hide()" :disabled="loading"></button>
				</div>

				<div class="modal-body">
					<div class="d-flex mb-3">
						<input
							type="search"
							class="form-control"
							placeholder="Buscar por nombre o correo…"
							v-model="search"
							@input="onSearchInput"
						/>
					</div>

					<div v-if="loading" class="text-muted">
						Cargando…
					</div>

					<div v-else>
						<div v-if="!rows.length" class="text-muted">
							No se encontraron usuarios.
						</div>

						<div v-else class="table-responsive">
							<table class="table table-sm align-middle mb-0">
								<thead>
									<tr class="text-muted text-uppercase">
										<th style="width: 80px;">ID</th>
										<th>Nombre</th>
										<th>Email</th>
										<th style="width: 140px;">Estatus</th>
										<th style="width: 140px;">Regalía actual</th>
										<th class="text-end" style="width: 140px;">Acciones</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="row in rows" :key="row.id">
										<td>#{{ row.id }}</td>
										<td>{{ row.display_name || '—' }}</td>
										<td>{{ row.email || '—' }}</td>
										<td>
											<span
												v-if="row.status === 'active'"
												class="badge rounded-pill text-bg-success"
											>
												activo
											</span>
											<span v-else class="badge rounded-pill text-bg-secondary">
												{{ row.status || '—' }}
											</span>
										</td>
										<td>
											<span v-if="row.is_assigned">
												{{ formatCommission(row.commission) }} %
											</span>
											<span v-else class="text-muted">
												—
											</span>
										</td>
										<td class="text-end">
											<button
												v-if="!row.is_assigned"
												type="button"
												class="btn btn-sm btn-primary"
												@click="attach(row)"
												:disabled="row._loading"
											>
												Añadir
											</button>
											<button
												v-else
												type="button"
												class="btn btn-sm btn-light-danger"
												@click="detach(row)"
												:disabled="row._loading"
											>
												Quitar
											</button>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div
							class="d-flex align-items-center justify-content-between mt-3"
							v-if="meta.total > meta.per_page"
						>
							<div class="text-muted small">
								Mostrando {{ meta.from }}–{{ meta.to }} de {{ meta.total }}
							</div>
							<div class="btn-group">
								<button
									type="button"
									class="btn btn-sm btn-light"
									:disabled="meta.current_page <= 1 || loading"
									@click="goToPage(meta.current_page - 1)"
								>
									Anterior
								</button>
								<button
									type="button"
									class="btn btn-sm btn-light"
									:disabled="meta.current_page >= meta.last_page || loading"
									@click="goToPage(meta.current_page + 1)"
								>
									Siguiente
								</button>
							</div>
						</div>
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-light" @click="hide()" :disabled="loading">
						Cerrar
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	props: {
		unitId: { type: [String, Number], required: true },
	},
	data() {
		return {
			modal: null,
			loading: false,
			search: '',
			searchTimer: null,
			rows: [],
			meta: {
				current_page: 1,
				last_page: 1,
				per_page: 20,
				total: 0,
				from: 0,
				to: 0,
			},
		};
	},
	methods: {
		open() {
			this.ensureModal();
			this.resetState();
			this.fetchPage(1);
			this.modal.show();
		},
		hide() {
			this.modal?.hide();
		},
		ensureModal() {
			if (!this.modal && typeof bootstrap !== 'undefined') {
				this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl);
			}
		},
		resetState() {
			this.loading = false;
			this.search = '';
					this.rows = [];
			this.meta = {
				current_page: 1,
				last_page: 1,
				per_page: 20,
				total: 0,
				from: 0,
				to: 0,
			};
		},
		onSearchInput() {
			if (this.searchTimer) {
				clearTimeout(this.searchTimer);
			}

			this.searchTimer = setTimeout(() => {
				this.fetchPage(1);
			}, 400);
		},
		async fetchPage(page) {
			if (!this.unitId) return;

			this.loading = true;

			try {
				const res = await axios.get(
					route('admin.business-units.api.units.gsa_commissions.available', {
						unit: this.unitId,
					}),
					{
						params: {
							page,
							per_page: this.meta.per_page,
							q: this.search,
						},
					}
				);

				const payload = res?.data || {};
				const list = Array.isArray(payload.data) ? payload.data : [];

				// El backend entrega la paginación en meta.pagination
				const metaRoot = payload.meta || {};
				const metaSrc = metaRoot.pagination || metaRoot || {};

				this.rows = list.map((row) => ({
					id: row.id,
					email: row.email,
					display_name: row.display_name,
					status: row.status,
					is_assigned: !!row.is_assigned,
					commission_user_id: row.commission_user_id || null,
					commission: typeof row.commission === 'number' ? row.commission : null,
					_loading: false,
				}));

				const total = typeof metaSrc.total === 'number' ? metaSrc.total : list.length;
				const perPage =
					typeof metaSrc.per_page === 'number' ? metaSrc.per_page : this.meta.per_page;
				const currentPage =
					typeof metaSrc.current_page === 'number' ? metaSrc.current_page : page;
				const lastPage =
					typeof metaSrc.last_page === 'number' ? metaSrc.last_page : currentPage;

				let from = metaSrc.from;
				let to = metaSrc.to;

				if (!from || !to) {
					if (list.length === 0) {
						from = 0;
						to = 0;
					} else {
						from = (currentPage - 1) * perPage + 1;
						to = from + list.length - 1;
					}
				}

				this.meta = {
					current_page: currentPage,
					last_page: lastPage,
					per_page: perPage,
					total,
					from,
					to,
				};
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(e?.response?.data?.message || 'Error al cargar usuarios.', 'danger');
				}
			} finally {
				this.loading = false;
			}
		},
		goToPage(page) {
			if (page < 1 || page > (this.meta.last_page || 1)) return;
			this.fetchPage(page);
		},
		formatCommission(value) {
			if (value === null || value === undefined) return '—';
			const num = Number(value);
			if (!Number.isFinite(num)) return String(value);
			return num.toString();
		},
		async attach(row) {
			if (!row || !this.unitId) return;

			row._loading = true;

			try {
				const res = await axios.post(
					route('admin.business-units.api.units.gsa_commissions.store', {
						unit: this.unitId,
					}),
					{
						user_id: row.id,
					}
				);

				const payload = res?.data?.data || null;

				row.is_assigned = true;
				row.commission_user_id = payload ? payload.id : null;
				row.commission = payload ? payload.commission : row.commission;

				this.$emit('user-attached', payload);

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(res?.data?.message || 'Usuario añadido a regalías.', 'success');
				}
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(e?.response?.data?.message || 'Error al añadir usuario.', 'danger');
				}
			} finally {
				row._loading = false;
			}
		},
		async detach(row) {
			if (!row || !row.commission_user_id || !this.unitId) return;

			if (!window.confirm('¿Quitar este usuario de las regalías?')) {
				return;
			}

			row._loading = true;

			try {
				const res = await axios.delete(
					route('admin.business-units.api.units.gsa_commissions.destroy', {
						unit: this.unitId,
						commissionUser: row.commission_user_id,
					})
				);

				const payload = res?.data?.data || {
					id: row.commission_user_id,
					user_id: row.id,
				};

				row.is_assigned = false;
				row.commission_user_id = null;
				row.commission = null;

				this.$emit('user-detached', payload);

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(res?.data?.message || 'Usuario removido de regalías.', 'success');
				}
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(e?.response?.data?.message || 'Error al remover usuario.', 'danger');
				}
			} finally {
				row._loading = false;
			}
		},
	},
};
</script>
