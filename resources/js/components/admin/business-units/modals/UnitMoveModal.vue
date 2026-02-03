<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Mover</h5>
					<button type="button" class="btn-close" @click="hide()"></button>
				</div>

				<div class="modal-body">
					<label class="form-label">parent_id (vacío = sin padre)</label>
					<input class="form-control" v-model="form.parent_id" placeholder="ID unidad padre" />
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
	props: { unitId: { type: [String, Number], required: true } },
	data() {
		return {
			modal: null,
			saving: false,
			form: { parent_id: '' },
		};
	},
	methods: {
		open(payload) {
			this.ensureModal();
			this.saving = false;
			this.form.parent_id = payload?.parent_id ? String(payload.parent_id) : '';
			this.modal.show();
		},
		hide() { this.modal?.hide(); },
		ensureModal() {
			if (!this.modal) this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl);
		},
		async save() {
			this.saving = true;
			try {
				const parent_id = (this.form.parent_id || '').trim() === '' ? null : Number(this.form.parent_id);
				const res = await axios.post(route('admin.business-units.api.units.move', { unit: this.unitId }), { parent_id });
				window.flash(res.data.message || 'Movida.', 'success');
				this.hide();
				this.$emit('saved');
			} catch (e) {
				window.flash(e?.response?.data?.message || 'Error', 'danger');
			} finally {
				this.saving = false;
			}
		}
	}
};
</script>
