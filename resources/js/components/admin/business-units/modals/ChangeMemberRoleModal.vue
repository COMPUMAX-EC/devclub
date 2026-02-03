<!-- resources/js/components/admin/business-units/modals/ChangeMemberRoleModal.vue -->
<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Cambiar rol</h5>
					<button type="button" class="btn-close" @click="hide()"></button>
				</div>

				<div class="modal-body">
					<div v-if="membership" class="mb-3">
						<div class="text-muted">Usuario</div>
						<div class="fw-semibold">
							{{ membership.user.display_name }} · #{{ membership.user.id }}
						</div>
						<div class="text-muted small">
							Membresía: {{ membership.status === 'inactive' ? 'inactiva' : 'activa' }}
						</div>
						<div class="text-muted small" v-if="membership.role">
							Rol actual: {{ membership.role.role_name }}
						</div>
						<div class="text-muted small" v-else>
							Sin rol asignado
						</div>
					</div>

					<div class="mb-3">
						<label class="form-label">Rol</label>
						<select class="form-select" v-model="role_id">
							<option value="">—</option>
							<option v-for="r in roles" :key="r.id" :value="r.id">
								{{ r.role_name }}
							</option>
						</select>
					</div>

					<div v-if="!canChangeCurrentMembership" class="alert alert-warning py-2">
						No tienes permisos para cambiar el rol de esta membresía.
					</div>
				</div>

				<div class="modal-footer">
					<button class="btn btn-light" @click="hide()">Cancelar</button>
					<button class="btn btn-primary" :disabled="saving || !canChangeCurrentMembership || !role_id"
						@click="save()">
						<span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status"></span>
						Guardar
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
			saving: false,

			// membresía objetivo
			membership: null,
			role_id: '',
			roles: [],

			// info necesaria para replicar canManageUser
			unit: null,          // incluye abilities
			myMembership: null,  // membresía del usuario logueado en esta unidad (si existe)
		};
	},
	computed: {
		/**
		 * Mismo criterio que canManageUser(m) en Show.vue,
		 * pero aplicado a la membresía actual del modal.
		 */
		canChangeCurrentMembership() {
			const m = this.membership;
			if (!m) return false;

			// 1) habilidad global "any": puede gestionar cualquier usuario de la unidad
			if (
				this.unit &&
				this.unit.abilities &&
				this.unit.abilities.can_members_manage_roles_any
			) {
				return true;
			}

			// 2) solo si tiene membresía propia
			if (!this.myMembership) {
				return false;
			}

			// requiere can_members_manage_roles
			const hasLocalManage =
				!!(
					this.unit &&
					this.unit.abilities &&
					this.unit.abilities.can_members_manage_roles
				);

			if (!hasLocalManage) {
				return false;
			}

			// nivel de rol: no puede tocar roles "más importantes" ni su propio rol
			if (!this.myMembership.role || !m.role) {
				// si alguno no tiene rol, por seguridad se deniega
				return false;
			}

			const myLevel = this.myMembership.role.level;
			const targetLevel = m.role.level;

			// no permitimos operar sobre roles más importantes
			if (myLevel > targetLevel) {
				return false;
			}

			// no puede modificar su propia membresía con permisos solo locales
			if (
				this.myMembership.user &&
				m.user &&
				this.myMembership.user.id === m.user.id
			) {
				return false;
			}

			return true;
		},
	},
	methods: {
		ensureModal() {
			if (!this.modal && this.$refs.modalEl) {
				this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl);
			}
		},
		hide() {
			this.modal?.hide();
		},
		async open(payload) {
			this.ensureModal();
			this.saving = false;

			this.membership = payload?.membership || null;
			this.role_id = this.membership?.role?.id || '';

			try {
				// Traemos todo lo necesario por AJAX:
				// - unidad (abilities)
				// - membresía del usuario logueado (via meta.current_membership)
				// - roles asignables en la unidad
				await Promise.all([
					this.loadUnit(),
					this.loadMyMembership(),
					this.loadRoles(),
				]);
			} catch (e) {
				window.flash(
					e?.response?.data?.message || 'Error cargando datos para el cambio de rol.',
					'danger'
				);
				return;
			}

			if (!this.canChangeCurrentMembership) {
				window.flash(
					'No tienes permisos para cambiar el rol de esta membresía.',
					'warning'
				);
			}

			this.modal.show();
		},
		async loadUnit() {
			const res = await axios.get(
				route('admin.business-units.api.units.show', { unit: this.unitId })
			);
			this.unit = res.data?.data || null;
		},
		async loadMyMembership() {
			try {
				const res = await axios.get(
					route('admin.business-units.api.units.members.list', { unit: this.unitId })
				);
				const meta = res.data?.meta || {};
				this.myMembership = meta.current_membership || null;
			} catch (e) {
				this.myMembership = null;
				throw e;
			}
		},
		async loadRoles() {
			const res = await axios.get(
				route('admin.business-units.api.roles.unit'),
				{
					params: {
						unit_id: this.unitId,
					},
				}
			);

			const data = res.data?.data || [];

			// Ordenar por level asc (nulls al final) y luego por nombre
			this.roles = data.slice().sort((a, b) => {
				const levelA = typeof a.level === 'number' ? a.level : 999999;
				const levelB = typeof b.level === 'number' ? b.level : 999999;

				if (levelA !== levelB) {
					return levelA - levelB;
				}

				// empate de level: ordenar por nombre "bonito"
				const nameA = (a.role_name || a.name || '').toLowerCase();
				const nameB = (b.role_name || b.name || '').toLowerCase();
				return nameA.localeCompare(nameB, 'es');
			});
		},
		async save() {
			if (!this.membership) return;

			if (!this.canChangeCurrentMembership) {
				window.flash(
					'No tienes permisos para cambiar el rol de esta membresía.',
					'danger'
				);
				return;
			}

			if (!this.role_id) {
				window.flash('Rol requerido.', 'warning');
				return;
			}

			this.saving = true;

			try {
				const res = await axios.patch(
					route('admin.business-units.api.units.members.update_role', {
						unit: this.unitId,
						membership: this.membership.id,
					}),
					{
						role_id: this.role_id,
					}
				);

				window.flash(res.data?.message || 'Rol actualizado.', 'success');
				this.hide();
				this.$emit('saved');
			} catch (e) {
				window.flash(
					e?.response?.data?.message || 'Error al actualizar el rol.',
					'danger'
				);
			} finally {
				this.saving = false;
			}
		},
	},
};
</script>
