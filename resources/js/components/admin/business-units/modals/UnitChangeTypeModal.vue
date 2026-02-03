<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Cambiar tipo</h5>
					<button type="button" class="btn-close" @click="hide()"></button>
				</div>

				<div class="modal-body">
					<div v-if="options.length === 0" class="text-muted">
						No hay conversiones disponibles para esta unidad.
					</div>

					<div v-else>
						<label class="form-label">Conversión</label>
						<select class="form-select" v-model="selectedKey">
							<option v-for="o in options" :key="o.key" :value="o.key">
								{{ o.label }}
							</option>
						</select>
					</div>
				</div>

				<div class="modal-footer">
					<button class="btn btn-light" @click="hide()">Cancelar</button>
					<button class="btn btn-primary" :disabled="saving || options.length === 0" @click="save()">Guardar</button>
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
			fromType: '',
			hasParent: false,
			selectedKey: '',
		};
	},
	computed: {
		options() {
			const out = [];

			// Reglas:
			// freelance -> office
			// office -> consolidator (solo si office NO tiene padre)
			// office -> office independiente (si tiene padre)
			// counter -> agencia independiente (counter -> office sin padre)

			if (this.fromType === 'freelance') {
				out.push({
					key: 'freelance_to_office',
					label: 'freelance → office',
					target_type: 'office',
					detach_parent: false,
				});
			}

			if (this.fromType === 'office') {
				if (this.hasParent) {
					out.push({
						key: 'office_to_office_independent',
						label: 'office → office independiente',
						target_type: 'office',
						detach_parent: true,
					});
				} else {
					out.push({
						key: 'office_to_consolidator',
						label: 'office → consolidator',
						target_type: 'consolidator',
						detach_parent: false,
					});
				}
			}

			if (this.fromType === 'counter') {
				out.push({
					key: 'counter_to_agency_independent',
					label: 'counter → agencia independiente',
					target_type: 'office',
					detach_parent: false,
				});
			}

			return out;
		},
		selectedOption() {
			return this.options.find(o => o.key === this.selectedKey) || null;
		},
	},
	methods: {
		open(payload) {
			this.ensureModal();
			this.saving = false;

			this.fromType = payload?.from_type || payload?.type || '';
			this.hasParent = !!payload?.parent_id;

			this.selectedKey = this.options[0]?.key || '';

			this.modal.show();
		},
		hide() { this.modal?.hide(); },
		ensureModal() {
			if (!this.modal) this.modal = bootstrap.Modal.getOrCreateInstance(this.$refs.modalEl);
		},
		async save() {
			if (!this.selectedOption) return;

			this.saving = true;
			try {
				const res = await axios.post(route('admin.business-units.api.units.change_type', { unit: this.unitId }), {
					target_type: this.selectedOption.target_type,
					detach_parent: this.selectedOption.detach_parent ? 1 : 0,
				});
				window.flash(res.data.message || 'Tipo actualizado.', 'success');
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
