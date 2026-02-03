<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Editar básico</h5>
					<button type="button" class="btn-close" @click="hide()"></button>
				</div>

				<div class="modal-body">
					<label class="form-label">Nombre</label>
					<input class="form-control" v-model="form.name" />
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
	},
	data() {
		return {
			modal: null,
			saving: false,
			form: { name: '' },
		};
	},
	methods: {
		open(payload) {
			this.ensureModal();
			this.saving = false;
			this.form.name = (payload?.name ?? '');
			this.modal.show();
		},
		hide() {
			this.modal?.hide();
		},
		ensureModal() {
			if (!this.modal) {
				this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl);
			}
		},
		async save() {
			const name = (this.form.name || '').trim();
			if (!name) {
				window.flash('Nombre requerido.', 'warning');
				return;
			}

			this.saving = true;
			try {
				const res = await axios.patch(route('admin.business-units.api.units.basic.update', { unit: this.unitId }), { name });
				window.flash(res.data.message || 'Guardado.', 'success');
				this.hide();
				this.$emit('saved');
			} catch (e) {
				window.flash(e?.response?.data?.message || 'Error', 'danger');
			} finally {
				this.saving = false;
			}
		},
	},
};
</script>
