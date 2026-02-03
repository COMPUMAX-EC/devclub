<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Vincular por correo</h5>
					<button type="button" class="btn-close" @click="hide()"></button>
				</div>

				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Rol</label>
						<select class="form-select" v-model="form.role_id">
							<option value="">—</option>
							<option
								v-for="r in roles"
								:key="r.id"
								:value="r.id"
							>
								{{ r.role_name || r.name }}
							</option>
						</select>
					</div>

					<div class="mb-3">
						<label class="form-label">Correo (exacto)</label>
						<input type="email" class="form-control" v-model="form.email" />
					</div>
				</div>

				<div class="modal-footer">
					<button class="btn btn-light" @click="hide()">Cancelar</button>
					<button class="btn btn-primary" :disabled="saving" @click="save()">Guardar</button>
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
	},
	data() {
		return {
			modal: null,
			saving: false,
			form: { role_id: '', email: '' },
		};
	},
	methods: {
		open() {
			this.ensureModal();
			this.saving = false;
			this.form = { role_id: '', email: '' };
			this.modal.show();
		},
		hide() { this.modal?.hide(); },
		ensureModal() {
			if (!this.modal) this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl);
		},
		async save() {
			const role_id = this.form.role_id;
			const email = (this.form.email || '').trim();

			if (!role_id) {
				window.flash('Rol requerido.', 'warning');
				return;
			}
			if (!email) {
				window.flash('Correo requerido.', 'warning');
				return;
			}

			this.saving = true;
			try {
				const res = await axios.post(route('admin.business-units.api.units.members.link', { unit: this.unitId }), {
					mode: 'email',
					role_id,
					email,
				});
				window.flash(res.data.message || 'Vinculado.', 'success');
				this.hide();
				this.$emit('linked');
			} catch (e) {
				window.flash(e?.response?.data?.message || 'Error', 'danger');
			} finally {
				this.saving = false;
			}
		}
	}
};
</script>
