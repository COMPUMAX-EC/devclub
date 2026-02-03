<!-- resources/js/components/admin/debug/UserUnitsDebug.vue -->
<template>
	<div class="card">
		<div class="card-header align-items-center">
			<div class="card-title flex-column">
				<h3 class="card-label fw-bold mb-1">
					Debug · Usuarios y unidades
				</h3>
				<div class="text-muted small">
					Lista de todos los usuarios con sus unidades y permisos efectivos por unidad.
				</div>
			</div>

			<div class="card-toolbar d-flex flex-wrap gap-3 justify-content-end">
				<div class="d-flex gap-2 align-items-center">
					<div class="w-250px">
						<input
							v-model="filters.q"
							type="text"
							class="form-control form-control-solid"
							placeholder="Buscar por nombre / email"
							@keyup.enter="fetchUsers(1)"
						/>
					</div>
				</div>
			</div>
		</div>

		<div class="card-body py-3">
			<div v-if="error" class="alert alert-danger py-2">
				{{ error }}
			</div>

			<div v-if="loading" class="text-muted">
				Cargando usuarios...
			</div>

			<div v-else class="table-responsive">
				<table class="table table-row-dashed align-middle">
					<thead>
						<tr class="fw-bold text-muted">
							<th class="min-w-250px">Usuario</th>
							<th class="min-w-350px">Unidades</th>
						</tr>
					</thead>

					<tbody>
						<tr v-if="users.length === 0">
							<td colspan="2" class="text-center text-muted py-8">
								Sin usuarios
							</td>
						</tr>

						<tr v-for="u in users" :key="u.id">
							<td class="align-top">
								<div class="d-flex flex-column">
									<div class="fw-semibold">
										{{ u.display_name || u.name || u.email || ('#' + u.id) }}
									</div>
									<div class="text-muted small">
										{{ u.email }} · #{{ u.id }}
									</div>
									<div class="text-muted small" v-if="u.status">
										Status: {{ u.status }}
									</div>
								</div>
							</td>

							<td class="align-top">
								<div v-if="u.memberships && u.memberships.length">
									<!-- lista raíz de unidades donde el usuario tiene membresía -->
									<ul class="mb-0">
										<li
											v-for="m in u.memberships"
											:key="m.id"
											class="mb-2"
										>
											<!-- Unidad raíz donde el usuario TIENE membresía -->
											<div>
												<a
													href="#"
													@click.prevent="openUnitModal(u, m.unit, m)"
												>
													{{ displayUnitName(m.unit) }}
												</a>
												<span class="text-muted small ms-1">
													· {{ m.unit.type }}
													<span
														v-if="m.unit.status"
														class="ms-1"
													>
														({{ m.unit.status }})
													</span>
												</span>
											</div>

											<div class="small mt-1">
												<span v-if="m.role">
													Rol:&nbsp;
													<span class="fw-semibold">
														{{ m.role.name }}
													</span>
												</span>
												<span v-if="m.status" class="ms-2">
													Membresía:
													<span
														class="badge badge-sm"
														:class="m.status === 'active'
															? 'badge-light-success'
															: 'badge-light-secondary'"
													>
														{{ m.status }}
													</span>
												</span>
											</div>

											<!-- Hijos directos de la unidad -->
											<ul
												v-if="childUnits(m.unit).length"
												class="ms-4 mt-1"
											>
												<li
													v-for="child in childUnits(m.unit)"
													:key="child.id"
													class="mb-1"
												>
													<div>
														<a
															href="#"
															@click.prevent="openUnitModal(
																u,
																child,
																getUserMembershipForUnit(u, child.id)
															)"
														>
															{{ displayUnitName(child) }}
														</a>
														<span class="text-muted small ms-1">
															· {{ child.type }}
															<span
																v-if="child.status"
																class="ms-1"
															>
																({{ child.status }})
															</span>
														</span>
													</div>

													<div class="small mt-1">
														<template v-if="getUserMembershipForUnit(u, child.id)">
															<span v-if="getUserMembershipForUnit(u, child.id).role">
																Rol:&nbsp;
																<span class="fw-semibold">
																	{{ getUserMembershipForUnit(u, child.id).role.name }}
																</span>
															</span>
															<span
																v-if="getUserMembershipForUnit(u, child.id).status"
																class="ms-2"
															>
																Membresía:
																<span
																	class="badge badge-sm"
																	:class="getUserMembershipForUnit(u, child.id).status === 'active'
																		? 'badge-light-success'
																		: 'badge-light-secondary'"
																>
																	{{ getUserMembershipForUnit(u, child.id).status }}
																</span>
															</span>
														</template>
														<span v-else class="text-muted">
															Sin membresía directa en esta
															unidad
														</span>
													</div>

													<!-- Hijos de los hijos (nietos) -->
													<ul
														v-if="childUnits(child).length"
														class="ms-4 mt-1"
													>
														<li
															v-for="grand in childUnits(child)"
															:key="grand.id"
															class="mb-1"
														>
															<div>
																<a
																	href="#"
																	@click.prevent="openUnitModal(
																		u,
																		grand,
																		getUserMembershipForUnit(u, grand.id)
																	)"
																>
																	{{ displayUnitName(grand) }}
																</a>
																<span class="text-muted small ms-1">
																	· {{ grand.type }}
																	<span
																		v-if="grand.status"
																		class="ms-1"
																	>
																		({{ grand.status }})
																	</span>
																</span>
															</div>

															<div class="small mt-1">
																<template v-if="getUserMembershipForUnit(u, grand.id)">
																	<span
																		v-if="getUserMembershipForUnit(u, grand.id).role"
																	>
																		Rol:&nbsp;
																		<span class="fw-semibold">
																			{{ getUserMembershipForUnit(u, grand.id).role.name }}
																		</span>
																	</span>
																	<span
																		v-if="getUserMembershipForUnit(u, grand.id).status"
																		class="ms-2"
																	>
																		Membresía:
																		<span
																			class="badge badge-sm"
																			:class="getUserMembershipForUnit(u, grand.id).status === 'active'
																				? 'badge-light-success'
																				: 'badge-light-secondary'"
																		>
																			{{ getUserMembershipForUnit(u, grand.id).status }}
																		</span>
																	</span>
																</template>
																<span v-else class="text-muted">
																	Sin membresía directa en
																	esta unidad
																</span>
															</div>
														</li>
													</ul>
												</li>
											</ul>
										</li>
									</ul>
								</div>

								<div v-else class="text-muted small">
									Sin unidades
								</div>
							</td>
						</tr>
					</tbody>
				</table>

				<div
					class="d-flex justify-content-between align-items-center mt-4"
					v-if="meta.pagination"
				>
					<div class="text-muted small">
						Página {{ meta.pagination.current_page }} de
						{{ meta.pagination.last_page }} · Total
						{{ meta.pagination.total }}
					</div>

					<div class="btn-group">
						<button
							class="btn btn-sm btn-light"
							@click="fetchUsers(meta.pagination.current_page - 1)"
							:disabled="loading || meta.pagination.current_page <= 1"
						>
							Anterior
						</button>
						<button
							class="btn btn-sm btn-light"
							@click="fetchUsers(meta.pagination.current_page + 1)"
							:disabled="loading || meta.pagination.current_page >= meta.pagination.last_page"
						>
							Siguiente
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal de permisos por unidad/usuario -->
		<div
			class="modal fade"
			tabindex="-1"
			ref="permsModal"
		>
			<div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-xxl-down">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">
							Permisos efectivos
							<span
								v-if="selectedUser && selectedUnit"
								class="text-muted small d-block mt-1"
							>
								{{ selectedUserLabel }} @ {{ selectedUnitLabel }}
							</span>
							<span
								v-if="selectedMembership"
								class="text-muted small d-block"
							>
								<span v-if="selectedMembership.role">
									Rol en unidad:
									<span class="fw-semibold">
										{{ selectedMembership.role.name }}
									</span>
								</span>
								<span v-if="selectedMembership.status" class="ms-2">
									Membresía:
									<span
										class="badge badge-sm"
										:class="selectedMembership.status === 'active'
											? 'badge-light-success'
											: 'badge-light-secondary'"
									>
										{{ selectedMembership.status }}
									</span>
								</span>
							</span>
							<span
								v-if="modalRefreshCount > 0"
								class="text-muted small d-block"
							>
								Refrescos: {{ modalRefreshCount }}
							</span>
						</h5>
						<button
							type="button"
							class="btn-close"
							@click="hideModal"
						></button>
					</div>

					<div class="">
						<div v-if="modalLoading" class="text-muted">
							Cargando permisos...
						</div>

						<div v-else-if="modalError" class="alert alert-danger py-2">
							{{ modalError }}
						</div>

						<div v-else-if="!modalDetails">
							<div class="text-muted">
								Sin datos.
							</div>
						</div>

						<!-- Habilidades efectivas -->
						<table
							v-else-if="modalDetails.abilities && Object.keys(modalDetails.abilities).length"
							class="table table-condensed table-row-dashed table-striped"
						>
							<thead>
								<tr>
									<th class="text-muted"></th>
									<th class="text-muted">Habilidad</th>
									<th class="text-muted">Requisito</th>
									<th class="text-muted">R</th>
									<th class="text-muted">L</th>
								</tr>
							</thead>
							<tbody>
								<tr
									v-for="(value, key) in modalDetails.abilities"
									:key="key"
								>
									<td>
										<span
											:class="value ? 'badge text-bg-success' : 'badge text-bg-secondary'"
											style="width: 1.5rem;"
										>
											{{ value ? '1':'0' }}
										</span>
									</td>
									<td class="text-muted align-middle" :class="{ 'main': value }">
										{{ key }}
									</td>
									<td class="align-middle">
										{{ abilityRequirementLabel(key) }}
									</td>
									<td class="align-middle" style="font-family: monospace;">
										{{ abilityRequirementLevel(key) }}
									</td>
									<td class="align-middle" style="font-family: monospace;">
										{{ modalDetails.permissions[abilityRequirementLabel(key)] }}
									</td>
								</tr>
							</tbody>
						</table>

						<div
							v-else
							class="text-muted small"
						>
							Sin habilidades cargadas.
						</div>
						
						
						

								<h6 class="fw-bold mb-2">Permisos efectivos</h6>

								<div
									v-if="modalDetails && modalDetails.permissions && Object.keys(modalDetails.permissions).length"
									class="border rounded p-2 small"
								>
									<table class="table table-condensed table-row-dashed">
										<thead>
											<tr>
												<th class="text-muted" style="width:90%;">Permiso</th>
												<th class="text-muted" style="width:20%;">L</th>
											</tr>
										</thead>
										<tbody>
											<tr
												v-for="(value, key) in modalDetails.permissions"
												:key="key"
											>
												<td class="text-muted align-middle" style="width:55%;">
													{{ key }}
												</td>
												<td class="align-middle text-center">
													<span v-if="typeof value === 'boolean'">
														<span
															:class="value ? 'badge text-bg-success' : 'badge text-bg-secondary'"
														>
															{{ value ? 'sí' : 'no' }}
														</span>
													</span>
													<span v-else>
														{{ value }}
													</span>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
						
					</div>

					<div class="modal-footer">
						<button
							type="button"
							class="btn btn-light"
							@click="hideModal"
						>
							Cerrar
						</button>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios'

const apiRoutes = {
	usersIndex: 'admin.debug.user-units.index',
	userUnitAbilities: 'admin.debug.user-units.abilities',
}

export default {
	name: 'AdminDebugUserUnits',

	data() {
		return {
			loading: false,
			error: null,
			users: [],
			meta: {},

			// listado completo de unidades (para bajar por el árbol)
			allUnits: [],

			filters: {
				q: '',
			},

			// Modal
			modal: null,
			modalLoading: false,
			modalError: null,
			modalDetails: null,
			selectedUser: null,
			selectedUnit: null,
			selectedMembership: null,

			// Auto-refresh del modal
			modalRefreshIntervalId: null,
			modalRefreshCount: 0,
		}
	},

	computed: {
		selectedUserLabel() {
			if (!this.selectedUser) return ''
			return (
				this.selectedUser.display_name ||
				this.selectedUser.name ||
				this.selectedUser.email ||
				('#' + this.selectedUser.id)
			)
		},
		selectedUnitLabel() {
			if (!this.selectedUnit) return ''
			return this.displayUnitName(this.selectedUnit)
		},
	},

	mounted() {
		this.fetchUsers(1)
	},

	methods: {
		displayUnitName(unit) {
			if (!unit) return '—'
			const name = (unit.name || '').trim()
			return name !== '' ? name : ('#' + unit.id)
		},

		apiUrl(name) {
			return route(apiRoutes[name])
		},

		async fetchUsers(page = 1) {
			this.loading = true
			this.error = null

			try {
				const url = this.apiUrl('usersIndex')
				const { data } = await axios.get(url, {
					params: {
						page,
						per_page: 25,
						q: this.filters.q || null,
					},
				})

				this.users = data.data || []
				this.meta = data.meta || {}
				// array plano de TODAS las unidades con parent_id
				this.allUnits = data.units || []
			} catch (e) {
				this.error = this.humanError(e) || 'Error cargando usuarios.'
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(this.error, 'danger')
				}
			} finally {
				this.loading = false
			}
		},

		/**
		 * Devuelve los hijos directos de una unidad (según parent_id),
		 * bajando por el árbol de BusinessUnit.
		 */
		childUnits(unit) {
			if (!unit || !this.allUnits || !this.allUnits.length) {
				return []
			}
			const parentId = unit.id
			return this.allUnits.filter(u => u.parent_id === parentId)
		},

		/**
		 * Devuelve la membresía del usuario para una unidad dada (si existe).
		 */
		getUserMembershipForUnit(user, unitId) {
			if (!user || !user.memberships || !user.memberships.length) {
				return null
			}
			return user.memberships.find(m => m.unit && m.unit.id === unitId) || null
		},

		// Modal helpers
		ensureModal() {
			if (!this.modal && this.$refs.permsModal) {
				this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.permsModal)
			}
		},

		hideModal() {
			this.stopModalAutoRefresh()
			this.selectedMembership = null
			if (this.modal) {
				this.modal.hide()
			}
		},

		/**
		 * Abre el modal para un usuario + unidad concreta.
		 * La membresía es opcional (puede ser null si no hay membresía directa).
		 */
		openUnitModal(user, unit, membership = null) {
			this.ensureModal()
			this.stopModalAutoRefresh()

			this.selectedUser = user
			this.selectedUnit = unit
			this.selectedMembership = membership
			this.modalDetails = null
			this.modalError = null
			this.modalLoading = true
			this.modalRefreshCount = 0

			this.modal.show()

			// Primera carga
			this.fetchUnitAbilities(user.id, unit.id, true)

			// Auto-refresh cada 3 segundos
			this.startModalAutoRefresh()
		},

		startModalAutoRefresh() {
			if (this.modalRefreshIntervalId !== null) {
				return
			}

			this.modalRefreshIntervalId = window.setInterval(() => {
				if (!this.selectedUser || !this.selectedUnit) {
					return
				}
				this.fetchUnitAbilities(this.selectedUser.id, this.selectedUnit.id)
			}, 3000)
		},

		stopModalAutoRefresh() {
			if (this.modalRefreshIntervalId !== null) {
				window.clearInterval(this.modalRefreshIntervalId)
				this.modalRefreshIntervalId = null
			}
		},

		async fetchUnitAbilities(userId, unitId, isInitial = false) {
			if (isInitial) {
				this.modalLoading = true
				this.modalDetails = null
				this.modalError = null
			} else {
				this.modalError = null
			}

			try {
				const url = this.apiUrl('userUnitAbilities')
				const { data } = await axios.get(url, {
					params: {
						user_id: userId,
						unit_id: unitId,
					},
				})

				this.modalDetails = data.data				
				this.modalRefreshCount += 1
			} catch (e) {
				this.modalError = this.humanError(e) || 'Error cargando permisos.'
			} finally {
				if (isInitial) {
					this.modalLoading = false
				}
			}
		},

		abilityRequirementLabel(key) {
			const details = this.modalDetails
			if (!details || !details.ability_requirements) {
				return '—'
			}
			const req = details.ability_requirements[key]
			if (!req) {
				return '—'
			}
			return `${req.permission}`
		},

		abilityRequirementLevel(key) {
			const details = this.modalDetails
			if (!details || !details.ability_requirements) {
				return '—'
			}
			const req = details.ability_requirements[key]
			if (!req) {
				return '—'
			}
			return `${req.min_level}`
		},

		humanError(e) {
			if (!e) return 'Error'
			const r = e.response
			if (r && r.data) {
				if (r.data.message) return r.data.message
				if (r.data.errors) {
					try {
						const all = Object.values(r.data.errors).flat()
						if (all.length) return all.join(' ')
					} catch (_) {
						// ignore
					}
				}
			}
			return e.message || 'Error'
		},
	},
}
</script>
