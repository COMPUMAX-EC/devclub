<!--resources/js/components/admin/business-units/modals/CreateUserModal.vue-->
<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">{{ modalTitle }}</h5>
					<button type="button" class="btn-close" @click="hide()" :disabled="saving"></button>
				</div>

				<div class="modal-body">
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">Nombre</label>
							<input type="text" class="form-control" v-model="form.first_name" />
						</div>
						<div class="col-md-6">
							<label class="form-label">Apellido</label>
							<input type="text" class="form-control" v-model="form.last_name" />
						</div>
						<div class="col-12">
							<label class="form-label">Correo</label>
							<input type="email" class="form-control" v-model="form.email" />
						</div>
					</div>

					<div v-if="requiresRole" class="mt-4">
						<label class="form-label">Rol</label>
						<select class="form-select" v-model="form.role_id" :disabled="rolesLoading">
							<option value="">—</option>
							<option v-for="r in roles" :key="r.id" :value="String(r.id)">
								{{ r.role_name }}
							</option>
						</select>

						<div v-if="rolesLoading" class="form-text text-muted">
							Cargando roles…
						</div>
						<div v-else-if="roles.length === 0" class="form-text text-muted">
							No tienes roles disponibles para asignar en esta unidad.
						</div>
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-light" @click="hide()" :disabled="saving">
						Cancelar
					</button>
					<button type="button" class="btn btn-primary" @click="save()"
						:disabled="saving || (requiresRole && !canSubmitWithRole)">
						<span v-if="saving">Guardando…</span>
						<span v-else>Guardar</span>
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios'

export default {
	props: {
		// Tipo de unidad: freelance | office | consolidator | counter | ...
		unitType: { type: String, required: true },
		// null => creación de unidad freelance raíz + usuario
		// != null => creación de usuario + membresía en esa unidad
		unitId: { type: [String, Number, null], default: null },
	},
	data() {
		return {
			modal: null,
			saving: false,
			rolesLoading: false,
			roles: [],
			form: {
				first_name: '',
				last_name: '',
				email: '',
				role_id: '',
			},
		}
	},
	computed: {
		isFreelanceRoot() {
			return (
				this.unitType === 'freelance' &&
				(this.unitId === null ||
					this.unitId === '' ||
					typeof this.unitId === 'undefined')
			)
		},
		requiresRole() {
			// Solo pedimos rol cuando estamos creando usuario + membresía
			// dentro de una unidad ya existente (no freelance root).
			return (
				!this.isFreelanceRoot &&
				this.unitId !== null &&
				this.unitId !== '' &&
				typeof this.unitId !== 'undefined'
			)
		},
		canSubmitWithRole() {
			if (!this.requiresRole) return true
			if (this.rolesLoading) return false
			if (this.roles.length === 0) return false
			return this.form.role_id !== ''
		},
		modalTitle() {
			if (this.isFreelanceRoot) {
				return 'Crear freelance'
			}
			if (this.requiresRole) {
				return 'Crear usuario y vincular'
			}
			return 'Crear usuario'
		},
	},
	methods: {
		open() {
			this.reset()
			this.ensureModal()

			if (this.requiresRole) {
				this.fetchRoles()
			}

			this.modal.show()
		},
		hide() {
			this.modal?.hide()
		},
		reset() {
			this.saving = false
			this.rolesLoading = false
			this.roles = []
			this.form = {
				first_name: '',
				last_name: '',
				email: '',
				role_id: '',
			}
		},
		ensureModal() {
			if (!this.modal) {
				this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl)
			}
		},
		async fetchRoles() {
			if (!this.requiresRole) return

			this.rolesLoading = true
			this.roles = []

			try {
				const res = await axios.get(
					this.route('admin.business-units.api.roles.unit'),
					{
						params: {
							unit_id: this.unitId,
						},
					}
				)
				this.roles = res.data?.data || []
			} catch (e) {
				this.roles = []
				if (
					typeof window !== 'undefined' &&
					typeof window.flash === 'function'
				) {
					window.flash(
						this.humanError(e) || 'Error cargando roles.',
						'danger'
					)
				}
			} finally {
				this.rolesLoading = false
			}
		},
		async save() {
			const first = (this.form.first_name || '').trim()
			const last = (this.form.last_name || '').trim()
			const email = (this.form.email || '').trim()

			if (!first || !last || !email) {
				if (
					typeof window !== 'undefined' &&
					typeof window.flash === 'function'
				) {
					window.flash(
						'Nombre, apellido y correo son requeridos.',
						'warning'
					)
				}
				return
			}

			if (this.requiresRole && !this.form.role_id) {
				if (
					typeof window !== 'undefined' &&
					typeof window.flash === 'function'
				) {
					window.flash('Rol requerido.', 'warning')
				}
				return
			}

			this.saving = true

			try {
				if (this.isFreelanceRoot) {
					// Caso: creación de unidad freelance + usuario propietario
					const res = await axios.post(
						this.route('admin.business-units.api.units.store'),
						{
							type: 'freelance',
							mode: 'new_user',
							user: {
								first_name: first,
								last_name: last,
								email,
							},
						}
					)

					if (
						typeof window !== 'undefined' &&
						typeof window.flash === 'function'
					) {
						window.flash(
							res.data?.message || 'Freelance creado.',
							'success'
						)
					}

					const unitId = res.data?.data?.id ?? null
					const memberships = unitId ? [{ unit_id: unitId }] : []

					this.$emit('members-created', memberships)
					this.hide()
				} else if (this.requiresRole) {
					// Caso: unidad existente (no freelance root) -> crear usuario + membresía
					const payload = {
						first_name: first,
						last_name: last,
						email,
						role_id: Number(this.form.role_id),
					}

					const res = await axios.post(
						this.route(
							'admin.business-units.api.units.members.create_user',
							{
								unit: this.unitId,
							}
						),
						payload
					)

					if (
						typeof window !== 'undefined' &&
						typeof window.flash === 'function'
					) {
						window.flash(
							res.data?.message || 'Usuario creado.',
							'success'
						)
					}

					const membership = res.data?.data?.membership || null
					const memberships = membership ? [membership] : []

					this.$emit('members-created', memberships)
					this.hide()
				} else {
					// Fallback teórico: unidad sin rol requerido (no debería darse)
					if (
						typeof window !== 'undefined' &&
						typeof window.flash === 'function'
					) {
						window.flash(
							'Configuración inválida del modal.',
							'danger'
						)
					}
				}
			} catch (e) {
				if (
					typeof window !== 'undefined' &&
					typeof window.flash === 'function'
				) {
					window.flash(
						this.humanError(e) || 'Error al guardar.',
						'danger'
					)
				}
			} finally {
				this.saving = false
			}
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
