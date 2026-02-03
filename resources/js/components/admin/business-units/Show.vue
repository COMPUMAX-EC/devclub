<!-- resources/js/components/admin/business-units/Show.vue -->
<template>
	<div v-if="loading" class="text-muted">Cargando...</div>

	<div v-else-if="!unit" class="text-muted">No disponible.</div>

	<div v-else>
		<div class="d-flex align-items-start justify-content-between mb-4">
			<div>
				<div class="text-muted">
					<span v-if="unit.status === 'active'">active</span>
					<span v-else>inactive</span>
				</div>
			</div>

			<div class="d-flex gap-2">
				<button class="btn btn-light" v-if="unit.abilities.can_basic_edit" @click="openEditBasic">Editar</button>
				<button class="btn btn-light" v-if="unit.abilities.can_toggle_status" @click="openStatus">Status</button>
				<button class="btn btn-light" v-if="unit.abilities.can_branding_manage" @click="openBranding">Branding</button>
				<button class="btn btn-light" v-if="unit.abilities.can_move" @click="openMove">Mover</button>
				<button class="btn btn-light" v-if="unit.abilities.can_change_type" @click="openType">Cambiar tipo</button>
			</div>
		</div>

		<!-- Miembros -->
		<div class="card mb-4">
			<div class="card-header d-flex align-items-center justify-content-between">
				<div class="fw-bold">Miembros</div>

				<div class="btn-group">
					<button
						type="button"
						class="btn btn-sm btn-primary dropdown-toggle"
						data-bs-toggle="dropdown"
						:disabled="!unit.abilities.can_members_manage_roles && !unit.abilities.can_members_invite"
					>
						Añadir
					</button>
					<ul class="dropdown-menu dropdown-menu-end">
						<li>
							<button
								class="dropdown-item"
								type="button"
								@click="openCreateUser"
								:disabled="!unit.abilities.can_members_manage_roles"
							>
								Crear usuario
							</button>
						</li>
						<li>
							<button
								class="dropdown-item"
								type="button"
								@click="openLinkMember"
								:disabled="!unit.abilities.can_members_invite"
							>
								Vincular existente
							</button>
						</li>
					</ul>
				</div>
			</div>

			<div v-if="membersLoading" class="card-body">Cargando...</div>

			<div v-else-if="sortedMembers.length === 0" class="card-body">Sin miembros.</div>

			<table v-else class="table table-row-dashed align-middle table-condensed table-striped">
				<thead>
					<tr class="text-muted text-uppercase">
						<th>Usuario</th>
						<th style="width:220px;">Rol</th>
						<th style="width:220px;" class="text-center">Membresía</th>
						<th style="width:260px;" class="text-end">Acciones</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="m in sortedMembers" :key="m.id">
						<td>
							<div class="fw-semibold d-flex align-items-center gap-2">
								<span>{{ m.user.display_name }}</span>
								<span
									v-if="m.user.status && m.user.status !== 'active'"
									class="badge rounded-pill text-bg-danger"
								>
									{{ m.user.status }}
								</span>
							</div>
							<div class="text-muted small">
								{{ m.user.email }}
							</div>
						</td>

						<!-- Rol como texto plano (con nivel) -->
						<td>
							<span v-if="m.role">
								{{ m.role.role_name }}
							</span>
							<span v-else>—</span>
						</td>

						<!-- Estatus membresía -->
						<td class="text-center">
							<span
								v-if="m.status === 'inactive'"
								class="badge rounded-pill text-bg-warning"
							>
								inactiva
							</span>
							<span
								v-else
								class="badge rounded-pill text-bg-success"
							>
								activa
							</span>
						</td>

						<td class="text-end">
							<div class="btn-group" v-if="canSeeMemberActions(m)">
								<button
									type="button"
									class="btn btn-sm btn-light dropdown-toggle"
									data-bs-toggle="dropdown"
								>
									Acciones
								</button>
								<ul class="dropdown-menu dropdown-menu-end">
									<li>
										<button
											class="dropdown-item"
											type="button"
											@click="openChangeRole(m)"
											:disabled="!canManageUser(m)"
										>
											Cambiar rol
										</button>
									</li>
									<li>
										<button
											class="dropdown-item"
											type="button"
											@click="toggleMemberStatus(m)"
											:disabled="!canManageUser(m)"
										>
											{{ m.status === 'inactive' ? 'Activar miembrosía' : 'Suspender miembrosía' }}
										</button>
									</li>
									<li><hr class="dropdown-divider" /></li>
									<li>
										<button
											class="dropdown-item text-danger"
											type="button"
											@click="removeMember(m)"
										>
											Remover membresía
										</button>
									</li>
								</ul>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Regalías -->
		<div class="card mb-4" v-if="unit.abilities.can_edit_gsa_commission">
			<div class="card-header d-flex align-items-center justify-content-between">
				<div>
					<div class="fw-bold">Regalías</div>
					<div class="text-muted fs-7">
						Usuarios que recibirán regalías por las ventas de esta unidad.
					</div>
				</div>
				<button
					type="button"
					class="btn btn-sm btn-primary"
					@click="openGsaCommissions"
				>
					Configurar en modal
				</button>
			</div>

			<div v-if="gsaCommissionsLoading" class="card-body text-muted small">
				Cargando configuraciones de regalías...
			</div>

			<div v-else-if="!gsaCommissions.length" class="card-body text-muted small">
				No hay usuarios configurados para regalías en esta unidad.
				Use el botón <strong>Configurar en modal</strong> para añadirlos.
			</div>

			<table v-else class="table table-row-dashed table-striped table-hover table-condensed mb-0">
				<thead>
					<tr class="text-muted text-uppercase fs-8">
						<th style="width:80px;">ID</th>
						<th>Nombre</th>
						<th>Email</th>
						<th style="width:180px;">Regalía</th>
						<th class="text-end" style="width:140px;">Acciones</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in gsaCommissions" :key="row.id">
						<!-- ID -->
						<td>#{{ row.user_id != null ? row.user_id : row.id }}</td>

						<!-- Nombre -->
						<td>
							<div class="fw-semibold d-flex align-items-center gap-2">
								<span>{{ row.user_display_name }}</span>
								<span
									v-if="row.user_status && row.user_status !== 'active'"
									class="badge rounded-pill text-bg-danger"
								>
									{{ row.user_status }}
								</span>
							</div>
						</td>

						<!-- Email -->
						<td>
							<div class="text-muted small">
								{{ row.user_email }}
							</div>
						</td>

						<!-- Regalía (inline, igual comportamiento que en companies/Edit.vue) -->
						<td>
							<div class="input-group input-group-sm">
								<input
									type="text"
									class="form-control text-end"
									v-model="row._commissionInput"
									inputmode="decimal"
									autocomplete="off"
									@input="onGsaCommissionInput(row, $event)"
								/>
								<span class="input-group-text">%</span>
							</div>
						</td>

						<!-- Acciones -->
						<td class="text-end">
							<button
								type="button"
								class="btn btn-sm btn-light-danger"
								@click="confirmAndDetachGsaCommission(row)"
							>
								Quitar
							</button>
						</td>
					</tr>
					<tr v-if="gsaCommissions.length === 0">
						<td colspan="5" class="text-muted small">
							No hay usuarios configurados para regalías.
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Hijos -->
		<div class="card" v-if="unit.type === 'consolidator' || unit.type === 'office'">
			<div class="card-header d-flex align-items-center justify-content-between">
				<div class="fw-bold">Otras unidades</div>

				<div class="d-flex gap-2 align-items-center">
					<button
						v-if="canCreateOfficeChild"
						class="btn btn-sm btn-primary"
						type="button"
						@click="openCreateChild('office')"
					>
						Crear&nbsp;office
					</button>

					<button
						v-if="canCreateCounterChild"
						class="btn btn-sm btn-primary"
						type="button"
						@click="openCreateChild('counter')"
					>
						Crear&nbsp;counter
					</button>

					<select
						class="form-select form-select-sm"
						v-model="childrenStatus"
						style="max-width:160px"
						@change="loadChildren"
					>
						<option value="active">active</option>
						<option value="inactive">inactive</option>
						<option value="all">all</option>
					</select>

					<button class="btn btn-sm btn-light" @click="loadChildren">Refrescar</button>
				</div>
			</div>

			<div v-if="childrenLoading" class="card-body">Cargando...</div>

			<div v-else-if="children.length === 0" class="card-body">Sin hijos.</div>

			<table v-else class="table table-row-dashed align-middle">
				<thead>
					<tr class="text-muted text-uppercase">
						<th>Unidad</th>
						<th>Tipo</th>
						<th style="width:140px;">Status</th>
						<th style="width:220px;" class="text-end">Acciones</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="c in children" :key="c.id">
						<td class="main">
							{{ displayUnitName(c) }}
						</td>
						<td>
							{{ c.type }}
						</td>
						<td>
							<span
								class="badge rounded-pill text-bg-success"
								v-if="c.status === 'active'"
							>
								active
							</span>
							<span
								class="badge rounded-pill text-bg-danger"
								v-else
							>
								inactive
							</span>
						</td>
						<td class="text-end">
							<a class="btn btn-sm btn-light me-2" :href="routeShow(c.id)">Entrar</a>
							<button
								class="btn btn-sm btn-light"
								:disabled="!c.abilities.can_toggle_status"
								@click="quickToggleChildStatus(c)"
							>
								Status
							</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Modals -->

		<admin-business-units-modals-create-unit-modal
			ref="createChildUnitModal"
			:unit-type="createChildType"
			:parent-id="createChildParentId"
			@created="onChildCreated"
		/>

		<admin-business-units-modals-unit-basic-modal
			ref="basicModal"
			:unit-id="unitId"
			@saved="reloadAll"
		/>

		<admin-business-units-modals-unit-move-modal
			ref="moveModal"
			:unit-id="unitId"
			@saved="reloadAll"
		/>

		<admin-business-units-modals-unit-change-type-modal
			ref="typeModal"
			:unit-id="unitId"
			@saved="reloadAll"
		/>

		<admin-business-units-modals-unit-branding-modal
			ref="brandingModal"
			:unit-id="unitId"
			@saved="reloadAll"
		/>

		<admin-business-units-modals-link-member-by-email-modal
			ref="linkByEmailModal"
			:unit-id="unitId"
			:roles="roles"
			@linked="onLinkedMember"
		/>

		<admin-business-units-modals-pick-active-user-for-member-modal
			ref="pickActiveUserForMemberModal"
			:unit-id="unitId"
			:roles="roles"
			:members="members"
			@created="onLinkedMember"
			@unlinked="onLinkedMember"
		/>

		<admin-business-units-modals-change-member-role-modal
			ref="changeRoleModal"
			:unit-id="unitId"
			@saved="loadMembers"
		/>

		<admin-business-units-modals-create-user-modal
			ref="createUserModal"
			:unit-id="unitId"
			:unit-type="unit?.type"
			@members-created="onMembersCreated"
		/>

		<admin-business-units-modals-gsa-commissions-modal
			ref="gsaCommissionsModal"
			:unit-id="unitId"
			@user-attached="onGsaCommissionsChanged"
			@user-detached="onGsaCommissionsChanged"
		/>
	</div>
</template>

<script>
import * as format from '@/utils/format';

export default {
	name: 'AdminBusinessUnitsShow',
	props: {
		unitId: { type: [String, Number], required: true }
	},
	data() {
		return {
			loading: false,
			unit: null,

			childrenLoading: false,
			children: [],
			childrenStatus: 'active',

			membersLoading: false,
			members: [],
			roles: [],

			// membresía del usuario autenticado en esta unidad (si existe)
			myMembership: null,

			// resumen de regalías (solo lectura en esta vista)
			gsaCommissionsLoading: false,
			gsaCommissions: [],

			// timers por fila para edición inline de % comisión (clave: `gsa-commission:<id>`)
			autosaveDelay:
				(window.__RUNTIME_CONFIG__ && window.__RUNTIME_CONFIG__.autosaveDelayMs) || 800,
			autosaveTimers: {},

			createChildType: 'office',
			createChildParentId: null,
		};
	},
	computed: {
		canCreateChildren() {
			if (!this.unit) return false;
			return !!(this.unit?.abilities?.can_manage_children || this.unit?.abilities?.can_create);
		},
		canCreateOfficeChild() {
			return this.canCreateChildren && this.unit && this.unit.type === 'consolidator';
		},
		canCreateCounterChild() {
			return this.canCreateChildren && this.unit && (this.unit.type === 'consolidator' || this.unit.type === 'office');
		},
		/**
		 * Miembros ordenados por:
		 * 1) role.level asc (nulls al final)
		 * 2) user.display_name asc
		 */
		sortedMembers() {
			const copy = Array.isArray(this.members) ? [...this.members] : [];
			copy.sort((a, b) => {
				const levelA = a.role && typeof a.role.level === 'number' ? a.role.level : 999999;
				const levelB = b.role && typeof b.role.level === 'number' ? b.role.level : 999999;

				if (levelA !== levelB) {
					return levelA - levelB;
				}

				const nameA = (a.user && a.user.display_name ? a.user.display_name : '').toLowerCase();
				const nameB = (b.user && b.user.display_name ? b.user.display_name : '').toLowerCase();

				return nameA.localeCompare(nameB, 'es');
			});
			return copy;
		},
	},
	mounted() {
		this.load();
	},
	methods: {
		routeShow(id) {
			return route('admin.business-units.show', { unit: id });
		},
		displayUnitName(u) {
			const name = (u?.name || '').trim();
			return name !== '' ? name : ('#' + u.id);
		},

		// ----- Carga principal -----
		async load() {
			this.loading = true;
			try {
				const res = await axios.get(
					route('admin.business-units.api.units.show', { unit: this.unitId })
				);
				this.unit = res.data.data;

				await Promise.all([
					this.loadChildren(),
					this.loadMembers(),
					this.loadRoles(),
					this.loadGsaCommissions(),
				]);
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(e?.response?.data?.message || 'Error al cargar', 'danger');
				}
			} finally {
				this.loading = false;
			}
		},
		async reloadAll() {
			await this.load();
		},

		// ----- Hijos -----
		async loadChildren() {
			this.childrenLoading = true;
			try {
				const res = await axios.get(
					route('admin.business-units.api.units.children', { unit: this.unitId }),
					{ params: { status: this.childrenStatus } }
				);
				this.children = res.data.data || [];
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(e?.response?.data?.message || 'Error al cargar hijos', 'danger');
				}
			} finally {
				this.childrenLoading = false;
			}
		},

		// ----- Miembros -----
		async loadMembers() {
			if (!this.unit || !this.unit.abilities.can_members_view) return;

			this.membersLoading = true;
			try {
				const res = await axios.get(
					route('admin.business-units.api.units.members.list', { unit: this.unitId })
				);
				this.members = res.data.data || [];

				const meta = res.data.meta || {};
				this.myMembership = meta.current_membership || null;
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(e?.response?.data?.message || 'Error al cargar miembros', 'danger');
				}
			} finally {
				this.membersLoading = false;
			}
		},

		async loadRoles() {
			try {
				const res = await axios.get(
					route('admin.business-units.api.roles.unit'),
					{
						params: {
							unit_id: this.unitId,
						},
					}
				);
				this.roles = res.data.data || [];
			} catch (e) {
				this.roles = [];
			}
		},

		// ----- Regalías (resumen en tarjeta) -----
		async loadGsaCommissions() {
			if (!this.unit || !this.unit.abilities || !this.unit.abilities.can_edit_gsa_commission) {
				this.gsaCommissions = [];
				this.gsaCommissionsLoading = false;
				return;
			}

			this.gsaCommissionsLoading = true;

			try {
				const res = await axios.get(
					route('admin.business-units.api.units.gsa_commissions.index', { unit: this.unitId })
				);
				const raw = Array.isArray(res.data.data) ? res.data.data : [];

				this.gsaCommissions = raw.map((item) => {
					// Aceptamos tanto commission_percent como commission, por si acaso
					const source =
						typeof item.commission_percent !== 'undefined'
							? item.commission_percent
							: item.commission;

					let numeric = null;
					if (source != null) {
						const n = Number(source);
						if (!Number.isNaN(n)) {
							numeric = Math.max(0, Math.min(100, n));
						}
					}

					const display = numeric !== null ? format.formatDecimal(numeric) : '';

					const user = item.user || null;

					return {
						...item,
						// Campos aplanados para la vista
						user_display_name: user ? user.display_name : '',
						user_email: user ? user.email : '',
						user_status: user ? user.status : null,

						commission_percent: numeric,
						// Estado de edición inline (igual patrón que en companies/Edit.vue)
						_commissionInput: display,
						_lastValidCommissionInput: display,
						// Último valor guardado correctamente (para revertir en error)
						_savedCommissionNumeric: numeric,
						_savedCommissionInput: display,
						_saving: false,
					};
				});
			} catch (e) {
				this.gsaCommissions = [];
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(
						e?.response?.data?.message || 'Error al cargar regalías',
						'danger'
					);
				}
			} finally {
				this.gsaCommissionsLoading = false;
			}
		},

		onGsaCommissionsChanged() {
			// Se llama desde el modal cuando se añade o quita un usuario.
			// Recargamos el resumen completo.
			this.loadGsaCommissions();
		},

		// Input inline de regalía (mismo comportamiento que companies/Edit.vue)
		onGsaCommissionInput(row, event) {
			if (!row) return;

			const raw =
				(event && event.target ? event.target.value : row._commissionInput) || '';

			const { normalized, display } = format.normalizeDecimalDigitsInput(raw);

			let numeric = null;
			if (normalized !== null && normalized !== '' && normalized !== undefined) {
				const n = Number(normalized);
				if (!Number.isNaN(n)) {
					numeric = n;
				}
			}

			// Límite 0–100; si se pasa, volvemos al último input válido
			if (numeric !== null && numeric > 100) {
				const prev = row._lastValidCommissionInput || '';
				row._commissionInput = prev;
				if (event && event.target) {
					event.target.value = prev;
				}
				return;
			}

			row._commissionInput = display;
			row._lastValidCommissionInput = display;
			row.commission_percent = numeric;

			this.scheduleGsaCommissionAutosave(row);
		},

		scheduleGsaCommissionAutosave(row) {
			if (!row || !row.id) return;

			if (!this.autosaveTimers) {
				this.autosaveTimers = {};
			}

			const key = `gsa-commission:${row.id}`;

			if (this.autosaveTimers[key]) {
				clearTimeout(this.autosaveTimers[key]);
			}

			this.autosaveTimers[key] = setTimeout(() => {
				this.autosaveTimers[key] = null;
				this.saveGsaCommissionPercent(row);
			}, this.autosaveDelay);
		},

		async saveGsaCommissionPercent(row) {
			if (!row || !row.id) return;

			const key = `gsa-commission:${row.id}`;
			if (this.autosaveTimers && this.autosaveTimers[key]) {
				clearTimeout(this.autosaveTimers[key]);
				this.autosaveTimers[key] = null;
			}

			// Valores previos guardados para poder revertir en caso de error
			const prevNumeric = row._savedCommissionNumeric;
			const prevInput = row._savedCommissionInput;

			const value =
				typeof row.commission_percent === 'number'
					? row.commission_percent
					: null;

			row._saving = true;

			try {
				await axios.patch(
					route('admin.business-units.api.units.gsa_commissions.update', {
						unit: this.unitId,
						commissionUser: row.id,
					}),
					{ commission: value },
					{
						headers: { 'Content-Type': 'application/json' },
					}
				);

				// Éxito: NO reemplazamos ningún valor en la vista.
				// Solo actualizamos el "último valor guardado" interno.
				row._savedCommissionNumeric = row.commission_percent;
				row._savedCommissionInput = row._commissionInput;

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash('Regalía guardada.', 'success');
				}
			} catch (e) {
				// Error: restaurar el valor anterior en la vista
				row.commission_percent = prevNumeric;
				row._commissionInput = prevInput;
				row._lastValidCommissionInput = prevInput;

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(
						e?.response?.data?.message || 'Error al guardar regalía.',
						'danger'
					);
				}
			} finally {
				row._saving = false;
			}
		},

		async confirmAndDetachGsaCommission(row) {
			if (!row) return;

			if (!window.confirm('¿Seguro que deseas quitar este usuario de las regalías?')) {
				return;
			}

			try {
				const res = await axios.delete(
					route('admin.business-units.api.units.gsa_commissions.destroy', {
						unit: this.unitId,
						commissionUser: row.id,
					})
				);

				// Quitamos localmente
				this.gsaCommissions = this.gsaCommissions.filter((r) => r.id !== row.id);

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(
						res?.data?.message || 'Usuario removido de regalías.',
						'success'
					);
				}
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(
						e?.response?.data?.message || 'Error al remover usuario de regalías.',
						'danger'
					);
				}
			}
		},

		// ----- Habilidades por membresía (según backend) -----
		canManageUser(m) {
			// 2 escenarios:
			// 1. tiene la habilidad can_members_manage_roles_any
			if (!!(this.unit && this.unit.abilities && this.unit.abilities.can_members_manage_roles_any)) {
				return true;
			}

			// 2. tiene la habilidad can_members_manage_roles
			// requiere membresía
			if (!this.myMembership) {
				return false;
			}

			return !!(this.unit && this.unit.abilities && this.unit.abilities.can_members_manage_roles)
				&& (this.myMembership.role.level <= m.role.level) && (this.myMembership.user.id != m.user.id);
		},
		canSeeMemberActions(m) {
			return this.canManageUser(m);
		},

		// ----- Modales unitarios -----
		openEditBasic() {
			this.$refs.basicModal.open({ name: this.unit.name });
		},
		openStatus() {
			this.$refs.statusModal.open({ status: this.unit.status });
		},
		openMove() {
			this.$refs.moveModal.open({ parent_id: this.unit.parent_id });
		},
		openType() {
			this.$refs.typeModal.open({
				from_type: this.unit.type,
				parent_id: this.unit.parent_id,
			});
		},
		openBranding() {
			this.$refs.brandingModal.open({ branding: this.unit.branding });
		},

		openCreateChild(type) {
			this.createChildType = type;
			this.createChildParentId = this.unitId;

			this.$nextTick(() => {
				this.$refs.createChildUnitModal.open({ parent_id: this.createChildParentId });
			});
		},

		onChildCreated() {
			if (typeof window !== 'undefined' && typeof window.flash === 'function') {
				window.flash('Guardado.', 'success');
			}
			this.loadChildren();
		},

		openLinkMember() {
			if (this.unit.abilities.can_pick_active_users) {
				this.$refs.pickActiveUserForMemberModal.open();
			} else {
				this.$refs.linkByEmailModal.open();
			}
		},

		onLinkedMember() {
			this.loadMembers();
		},

		openChangeRole(m) {
			if (!this.canManageUser(m)) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(
						'No tienes permisos para cambiar el rol de esta membresía.',
						'danger'
					);
				}
				return;
			}
			this.$refs.changeRoleModal.open({ membership: m });
		},

		async toggleMemberStatus(m) {
			if (!this.canManageUser(m)) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(
						'No tienes permisos para cambiar el estatus de esta membresía.',
						'danger'
					);
				}
				return;
			}

			const previousStatus = m.status;
			const nextStatus = m.status === 'inactive' ? 'active' : 'inactive';

			// cambio optimista en el front
			m.status = nextStatus;

			try {
				const res = await axios.patch(
					route('admin.business-units.api.units.members.update_status', {
						unit: this.unitId,
						membership: m.id,
					}),
					{ status: nextStatus }
				);

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(
						res.data?.message || 'Estatus de membresía actualizado.',
						'success'
					);
				}
			} catch (e) {
				// revertir cambio local si el backend falla
				m.status = previousStatus;

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(
						e?.response?.data?.message || 'Error al actualizar estatus',
						'danger'
					);
				}
			}
		},

		async removeMember(m) {
			if (!confirm('¿Remover membresía?')) return;

			try {
				const res = await axios.delete(
					route('admin.business-units.api.units.members.remove', {
						unit: this.unitId,
						membership: m.id
					})
				);
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(res.data.message || 'Eliminado', 'success');
				}
				await this.loadMembers();
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(e?.response?.data?.message || 'Error', 'danger');
				}
			}
		},

		async quickToggleChildStatus(c) {
			const next = c.status === 'active' ? 'inactive' : 'active';
			try {
				const res = await axios.patch(
					route('admin.business-units.api.units.status.update', { unit: c.id }),
					{ status: next }
				);
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(res.data.message || 'Guardado', 'success');
				}
				await this.loadChildren();
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(e?.response?.data?.message || 'Error', 'danger');
				}
			}
		},

		openCreateUser() {
			this.$refs.createUserModal.open({
				unitId: this.unitId,
				unitType: this.unit?.type || null,
			});
		},

		onMembersCreated(members) {
			if (Array.isArray(members) && members.length > 0) {
				// payload si lo necesitas
			}
			this.loadMembers();
		},

		openGsaCommissions() {
			if (!this.$refs.gsaCommissionsModal) return;
			this.$refs.gsaCommissionsModal.open();
		},
	}
};
</script>
