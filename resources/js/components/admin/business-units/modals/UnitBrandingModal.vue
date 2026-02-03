<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Branding</h5>
					<button type="button" class="btn-close" @click="hide()"></button>
				</div>

				<div class="modal-body">
					<div class="row">
						<div class="col-md-6 mb-3">
							<label class="form-label">branding_text_dark</label>
							<input class="form-control" v-model="form.branding_text_dark" />
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label">branding_bg_light</label>
							<input class="form-control" v-model="form.branding_bg_light" />
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label">branding_text_light</label>
							<input class="form-control" v-model="form.branding_text_light" />
						</div>
						<div class="col-md-6 mb-3">
							<label class="form-label">branding_bg_dark</label>
							<input class="form-control" v-model="form.branding_bg_dark" />
						</div>
					</div>

					<div class="mb-3">
						<label class="form-label">Logo (opcional)</label>
						<input class="form-control" type="file" ref="logoInputEl" />
					</div>

					<div class="form-check">
						<input class="form-check-input" type="checkbox" v-model="form.remove_logo" id="removeLogoChk">
						<label class="form-check-label" for="removeLogoChk">
							Remover logo
						</label>
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
	props: { unitId: { type: [String, Number], required: true } },
	data() {
		return {
			modal: null,
			saving: false,
			form: {
				branding_text_dark: '',
				branding_bg_light: '',
				branding_text_light: '',
				branding_bg_dark: '',
				remove_logo: false,
			},
		};
	},
	methods: {
		open(payload) {
			this.ensureModal();
			this.saving = false;

			const b = payload?.branding || {};
			this.form.branding_text_dark = b.branding_text_dark || '';
			this.form.branding_bg_light = b.branding_bg_light || '';
			this.form.branding_text_light = b.branding_text_light || '';
			this.form.branding_bg_dark = b.branding_bg_dark || '';
			this.form.remove_logo = false;

			if (this.$refs.logoInputEl) this.$refs.logoInputEl.value = '';
			this.modal.show();
		},
		hide() { this.modal?.hide(); },
		ensureModal() {
			if (!this.modal) this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl);
		},
		async save() {
			this.saving = true;
			try {
				const form = new FormData();
				form.append('branding_text_dark', this.form.branding_text_dark);
				form.append('branding_bg_light', this.form.branding_bg_light);
				form.append('branding_text_light', this.form.branding_text_light);
				form.append('branding_bg_dark', this.form.branding_bg_dark);
				form.append('remove_logo', this.form.remove_logo ? '1' : '0');

				const file = this.$refs.logoInputEl?.files?.[0] || null;
				if (file) form.append('logo', file);

				const res = await axios.post(route('admin.business-units.api.units.branding.update', { unit: this.unitId }), form, {
					headers: { 'Content-Type': 'multipart/form-data' }
				});
				window.flash(res.data.message || 'Guardado.', 'success');
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
