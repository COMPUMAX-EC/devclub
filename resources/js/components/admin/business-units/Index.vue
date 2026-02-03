<!-- resources/js/components/admin/business-units/Index.vue -->
<template>
	<div>
		<div class="d-flex flex-stack mb-4">
			<div>
				<h1 class="h3 m-0">{{ title }}</h1>
				<div class="text-muted">Listado raíz</div>
			</div>

			<div class="d-flex gap-2">
				<button
					v-if="isFreelance"
					type="button"
					class="btn btn-light-primary"
					:disabled="!permissions.can_create"
					@click="openCreateFreelance()"
				>
					Crear
				</button>

				<button
					v-if="isFreelance"
					type="button"
					class="btn btn-primary"
					:disabled="!permissions.can_create"
					@click="openAddExistingFreelance()"
				>
					Añadir existente
				</button>

				<button
					v-if="!isFreelance"
					type="button"
					class="btn btn-primary"
					:disabled="!permissions.can_create"
					@click="openCreateUnit()"
				>
					Crear
				</button>
			</div>
		</div>

		<div class="card">
			<div class="card-header py-3">
				<div class="d-flex flex-stack gap-3 w-100">
					<div class="d-flex align-items-center gap-2">
						<label class="form-label m-0">Estado</label>
						<select class="form-select form-select-sm w-auto" v-model="filters.status" @change="fetchUnits()">
							<option value="active">Activos</option>
							<option value="inactive">Inactivos</option>
							<option value="all">Todos</option>
						</select>
					</div>

					<div class="d-flex align-items-center gap-2">
						<input
							type="text"
							class="form-control form-control-sm"
							placeholder="Buscar por nombre / ID"
							v-model="filters.q"
							@keyup.enter="fetchUnits()"
							style="max-width: 320px;"
						/>
						<button type="button" class="btn btn-sm btn-light" @click="fetchUnits()">Buscar</button>
					</div>
				</div>
			</div>

			<div class="card-body">
				<div v-if="loading" class="text-muted">Cargando...</div>

				<div v-else>
					<div v-if="units.length === 0" class="text-muted">Sin resultados.</div>

					<div v-else class="table-responsive">
						<table class="table table-row-dashed align-middle">
							<thead>
								<tr class="text-gray-600 fw-bold">
									<th>Nombre</th>
									<th v-if="!isFreelance">Padre</th>
									<th>Estado</th>
									<th class="text-end">Miembros</th>
									<th class="text-end">Acciones</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="u in units" :key="u.id">
									<td class="fw-semibold">
										<span>{{ displayUnitName(u) }}</span>
										<div class="text-muted small">#{{ u.id }}</div>
									</td>

									<td v-if="!isFreelance">
										<span v-if="u.parent">{{ u.parent.name }}</span>
										<span v-else class="text-muted">—</span>
									</td>

									<td>
										<span class="badge" :class="statusBadge(u.status)">
											{{ u.status }}
										</span>
									</td>

									<td class="text-end">
										<span class="text-muted">{{ typeof u.members_count === 'number' ? u.members_count : '—' }}</span>
									</td>

									<td class="text-end">
										<div class="d-inline-flex gap-2">
											<a class="btn btn-sm btn-light" :href="routeShow(u.id)">Entrar</a>

											<button
												v-if="canToggleStatus(u)"
												class="btn btn-sm btn-light"
												@click.prevent="toggleStatus(u)"
												:disabled="isToggleDisabled(u)"
												:title="toggleTitle(u)"
											>
												<span v-if="isBusy(u)">Procesando...</span>
												<span v-else>
													{{ u.status === 'active' ? 'Suspender' : 'Activar' }}
												</span>
											</button>
										</div>
									</td>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="d-flex flex-stack mt-4">
						<div class="text-muted small">
							Total: {{ pagination.total }}
						</div>

						<div class="d-flex gap-2">
							<button class="btn btn-sm btn-light" :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">
								Anterior
							</button>
							<button class="btn btn-sm btn-light" :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">
								Siguiente
							</button>
						</div>
					</div>

				</div>
			</div>
		</div>

		<!-- Modales -->
		<admin-business-units-modals-create-unit-modal
			ref="createUnitModal"
			:unit-type="unitType"
			@created="onCreatedUnit"
		/>

		<admin-business-units-modals-create-user-modal
			ref="createUserModal"
			@confirm="onConfirmCreateUser"
		/>

		<admin-business-units-modals-pick-active-user-modal
			ref="pickActiveUserModal"
			@confirm="onConfirmPickUser"
		/>

		<admin-business-units-modals-link-user-by-email-modal
			ref="linkByEmailModal"
			@confirm="onConfirmEmailExact"
		/>
	</div>
</template>

<script>
import axios from 'axios'

export default {
	props: {
		unitType: { type: String, required: true }, // consolidator|office|freelance
	},
	data() {
		return {
			loading: false,
			units: [],
			filters: {
				status: 'active',
				q: '',
				root: true,
				page: 1,
				per_page: 25,
			},
			pagination: {
				current_page: 1,
				last_page: 1,
				per_page: 25,
				total: 0,
			},
			busyByUnitId: {}, // { [id]: true }
			permissions: {
				can_create: false,
				can_pick_active_users: false,
			},
		};
	},
	computed: {
		isFreelance() {
			return this.unitType === 'freelance';
		},
		title() {
			if (this.unitType === 'consolidator') return 'Consolidadoras';
			if (this.unitType === 'office') return 'Oficinas';
			if (this.unitType === 'freelance') return 'Freelances';
			return 'Unidades';
		},
	},
	mounted() {
		this.fetchUnits();
	},
	methods: {
		routeShow(id) {
			return this.route('admin.business-units.show', { unit: id });
		},
		displayUnitName(u) {
			const name = (u?.name || '').trim();
			return name !== '' ? name : ('#' + u.id);
		},
		statusBadge(status) {
			if (status === 'active') return 'badge-light-success';
			if (status === 'inactive') return 'badge-light-danger';
			return 'badge-light';
		},
		goPage(p) {
			this.filters.page = p;
			this.fetchUnits();
		},

		isBusy(u) {
			const id = u?.id
			return !!(id && this.busyByUnitId[id])
		},

		// Se guía solo por la habilidad efectiva calculada en backend
		canToggleStatus(u) {
			const a = (u && u.abilities) || {}
			return a.can_toggle_status === true
		},

		isToggleDisabled(u) {
			return this.isBusy(u) || !this.canToggleStatus(u)
		},

		toggleTitle(u) {
			if (this.isBusy(u)) return 'Procesando...'
			if (!this.canToggleStatus(u)) return 'Sin permisos'
			return ''
		},

		baseUnitsApiUrl() {
			try {
				return route('admin.business-units.api.units')
			} catch (e) {
				return '/admin/business-units/api/units'
			}
		},

		toggleStatusUrl(u) {
			// Preferir URLs explícitas si el backend las entrega
			const a = u?.abilities || {}
			if (a.toggle_status_url) return a.toggle_status_url
			if (a.toggleStatusUrl) return a.toggleStatusUrl
			if (u?.toggle_status_url) return u.toggle_status_url
			if (u?.toggleStatusUrl) return u.toggleStatusUrl

			// Fallback basado en el endpoint RESTful de estado
			const base = String(this.baseUnitsApiUrl()).replace(/\/+$/, '')
			return `${base}/${u.id}/status`
		},

		async toggleStatus(u) {
			const id = u?.id
			if (!id) return
			if (!this.canToggleStatus(u)) return
			if (this.isBusy(u)) return

			const nextStatus = u.status === 'active' ? 'inactive' : 'active'
			const actionLabel = nextStatus === 'active' ? 'activar' : 'suspender'

			const ok = window.confirm(`¿Confirmas ${actionLabel} esta unidad?`)
			if (!ok) return

			this.$set?.(this.busyByUnitId, id, true) || (this.busyByUnitId[id] = true)

			try {
				const url = this.toggleStatusUrl(u)

				// Enviamos el estado destino al endpoint RESTful de status
				await axios.patch(url, { status: nextStatus })

				// UI optimista
				u.status = nextStatus

				if (typeof window.flash === 'function') {
					window.flash(
						`Unidad ${nextStatus === 'active' ? 'activada' : 'suspendida'} correctamente.`,
						'success'
					)
				}
			} catch (e) {
				if (typeof window.flash === 'function') {
					window.flash(this.humanError(e) || 'No se pudo actualizar el estado.', 'danger')
				}
			} finally {
				this.$set?.(this.busyByUnitId, id, false) || (this.busyByUnitId[id] = false)
			}
		},

		async fetchUnits() {
			this.loading = true;
			try {
				const params = {
					type: this.unitType,
					status: this.filters.status,
					q: this.filters.q || undefined,
					root: this.filters.root, // boolean
					page: this.filters.page,
					per_page: this.filters.per_page,
				};

				const res = await axios.get(this.route('admin.business-units.api.units'), { params });

				this.units = res.data.data || [];
				const meta = res.data.meta || {};
				this.pagination = meta.pagination || this.pagination;
				this.permissions = meta.permissions || this.permissions;

			} catch (e) {
				window.flash(this.humanError(e), 'danger');
			} finally {
				this.loading = false;
			}
		},

		openCreateUnit() {
			this.$refs.createUnitModal.open();
		},

		openCreateFreelance() {
			this.$refs.createUserModal.open();
		},

		openAddExistingFreelance() {
			if (this.permissions.can_pick_active_users) {
				this.$refs.pickActiveUserModal.open();
			} else {
				this.$refs.linkByEmailModal.open();
			}
		},

		async onConfirmCreateUser(payload) {
			try {
				await axios.post(this.route('admin.business-units.api.units.store'), {
					type: 'freelance',
					mode: 'new_user',
					user: payload,
				});
				window.flash('Freelance creado.', 'success');
				this.filters.page = 1;
				await this.fetchUnits();
			} catch (e) {
				window.flash(this.humanError(e), 'danger');
			}
		},

		async onConfirmPickUser(payload) {
			try {
				await axios.post(this.route('admin.business-units.api.units.store'), {
					type: 'freelance',
					mode: 'existing_user',
					existing_user_id: payload.user_id,
				});
				window.flash('Freelance creado.', 'success');
				this.filters.page = 1;
				await this.fetchUnits();
			} catch (e) {
				window.flash(this.humanError(e), 'danger');
			}
		},

		async onConfirmEmailExact(payload) {
			try {
				await axios.post(this.route('admin.business-units.api.units.store'), {
					type: 'freelance',
					mode: 'email_exact',
					email: payload.email,
				});
				window.flash('Freelance creado.', 'success');
				this.filters.page = 1;
				await this.fetchUnits();
			} catch (e) {
				window.flash(this.humanError(e), 'danger');
			}
		},

		onCreatedUnit() {
			window.flash('Guardado.', 'success');
			this.filters.page = 1;
			this.fetchUnits();
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
