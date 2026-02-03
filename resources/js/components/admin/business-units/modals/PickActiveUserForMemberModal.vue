<!--resources/js/components/admin/business-units/modals/PickActiveUserForMemberModal.vue-->
<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Usuarios activos</h5>
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
						<button class="btn btn-light" type="button" @click="fetchUsers(1)">
							Buscar
						</button>
					</div>

					<div v-if="loading" class="text-muted">Cargando...</div>

					<div v-else>
						<div v-if="users.length === 0" class="text-muted">Sin resultados.</div>

						<div v-else class="table-responsive">
							<table class="table table-hover table-condensed table-striped align-middle table-row-dashed">
								<thead>
									<tr class="text-gray-600 fw-bold">
										<th>Usuario</th>
										<th>Email</th>
										<th style="width:260px;">Rol en la unidad</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="u in users" :key="u.id">
										<td class="main">
											<div class="fw-semibold">
												{{ u.display_name || ((u.first_name || '') + ' ' + (u.last_name || '')) }}
											</div>
										</td>
										<td>{{ u.email }}</td>
										<td>
											<select
												class="form-select form-select-sm"
												:class="selectClassForUser(u)"
												v-model="selected[u.id]"
												:disabled="!!saving[u.id]"
												@change="onChangeRole(u)"
											>
												<option value="">Ninguno</option>
												<option
													v-for="r in roles"
													:key="r.id"
													:value="String(r.id)"
												>
													{{ r.role_name }}
												</option>
											</select>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="d-flex flex-stack mt-3">
							<div class="text-muted small">
								Total: {{ pagination.total }}
							</div>
							<div class="d-flex gap-2">
								<button
									class="btn btn-sm btn-light"
									:disabled="pagination.current_page <= 1 || loading"
									@click="fetchUsers(pagination.current_page - 1)"
								>
									Anterior
								</button>
								<button
									class="btn btn-sm btn-light"
									:disabled="pagination.current_page >= pagination.last_page || loading"
									@click="fetchUsers(pagination.current_page + 1)"
								>
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
	props: {
		unitId: { type: [String, Number], required: true },
		roles: { type: Array, required: true },
		members: { type: Array, required: true },
	},
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
			// rol seleccionado por user_id
			selected: {},
			// estado de guardado por user_id
			saving: {},
		};
	},
	computed: {
		// mapa user_id -> membership (de la unidad actual)
		membershipByUserId() {
			const map = {};
			(this.members || []).forEach(m => {
				if (m && m.user) {
					map[m.user.id] = m;
				}
			});
			return map;
		},
	},
	watch: {
		// cuando el padre recarga miembros, sincronizamos los selects
		members: {
			handler() {
				this.syncSelectedFromMembers();
			},
			deep: true,
		},
	},
	methods: {
		open() {
			this.ensureModal();
			this.q = '';
			this.users = [];
			this.selected = {};
			this.saving = {};
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
					params: {
						q: this.q || undefined,
						page,
						per_page: 15,
					},
				});
				this.users = res.data.data || [];
				this.pagination = res.data.meta?.pagination || this.pagination;
				this.syncSelectedFromMembers();
			} catch (e) {
				window.flash(this.humanError(e), 'danger');
			} finally {
				this.loading = false;
			}
		},
		// ajusta selected[u.id] según la membresía actual de cada usuario
		syncSelectedFromMembers() {
			const map = this.membershipByUserId;
			const next = { ...this.selected };
			this.users.forEach(u => {
				const m = map[u.id];
				next[u.id] = m && m.role ? String(m.role.id) : '';
			});
			this.selected = next;
		},
		selectClassForUser(u) {
			const m = this.membershipByUserId[u.id];
			const hasRole = !!(m && m.role);
			return hasRole ? 'membership-has-role' : 'membership-no-role';
		},
		async onChangeRole(u) {
			const userId = u.id;
			const map = this.membershipByUserId;
			const membership = map[userId] || null;
			const prevRoleId = membership && membership.role ? String(membership.role.id) : '';
			const newRoleId = (this.selected[userId] || '').toString();

			// nada cambió
			if (newRoleId === prevRoleId) {
				return;
			}

			// evitar doble clic sobre el mismo usuario
			if (this.saving[userId]) {
				return;
			}
			this.$set ? this.$set(this.saving, userId, true) : (this.saving[userId] = true);

			try {
				// "Ninguno" => eliminar membresía si existe
				if (newRoleId === '') {
					if (!membership) {
						// no hay membresía que eliminar; volvemos al estado "Ninguno"
						this.selected[userId] = '';
						return;
					}

					const res = await axios.delete(
						this.route('admin.business-units.api.units.members.remove', {
							unit: this.unitId,
							membership: membership.id,
						})
					);

					window.flash(res.data?.message || 'Membresía eliminada.', 'success');
					this.$emit('unlinked', {
						user_id: userId,
						membership_id: membership.id,
					});

					// el padre recargará members; el watcher sincroniza los selects
					return;
				}

				// hay rol seleccionado
				const roleIdNum = Number(newRoleId);

				if (membership) {
					// actualizar rol existente
					const res = await axios.patch(
						this.route('admin.business-units.api.units.members.update_role', {
							unit: this.unitId,
							membership: membership.id,
						}),
						{
							role_id: roleIdNum,
						}
					);

					window.flash(res.data?.message || 'Rol actualizado.', 'success');
					this.$emit('created', {
						user_id: userId,
						membership_id: membership.id,
						role_id: roleIdNum,
					});
				} else {
					// crear nueva membresía
					const res = await axios.post(
						this.route('admin.business-units.api.units.members.link', {
							unit: this.unitId,
						}),
						{
							mode: 'user_id',
							user_id: userId,
							role_id: roleIdNum,
						}
					);

					window.flash(res.data?.message || 'Usuario vinculado.', 'success');
					this.$emit('created', {
						user_id: userId,
						membership_id: null,
						role_id: roleIdNum,
					});
				}
				// el padre recargará members; watcher actualizará los selects
			} catch (e) {
				// revertimos el valor en caso de error
				this.selected[userId] = prevRoleId;
				window.flash(this.humanError(e) || 'Error al guardar.', 'danger');
			} finally {
				this.$set ? this.$set(this.saving, userId, false) : (this.saving[userId] = false);
			}
		},
		humanError(e) {
			if (!e) return 'Error';
			const r = e.response;
			if (r && r.data) {
				if (r.data.message) return r.data.message;
				if (r.data.errors) {
					try {
						const all = Object.values(r.data.errors).flat();
						if (all.length) return all.join(' ');
					} catch (_) {
						// ignore
					}
				}
			}
			return e.message || 'Error';
		},
	},
};
</script>

<style scoped>
/* Usuarios que YA tienen rol en la unidad */
.membership-has-role {
	font-weight: 600;
	color: #000;
}
.membership-has-role option {
	font-weight: 600;
	color: #000;
}

/* Usuarios SIN rol en la unidad */
.membership-no-role {
	font-weight: 400;
	color: #666;
}
.membership-no-role option {
	font-weight: 400;
	color: #666;
}
</style>
