<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">{{ modalTitle }}</h5>
					<button type="button" class="btn-close" @click="hide()"></button>
				</div>

				<div class="modal-body">
					<div class="mb-3">
						<label class="form-label">Nombre</label>
						<input type="text" class="form-control" v-model="form.name" />
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-light" @click="hide()">Cancelar</button>
					<button type="button" class="btn btn-primary" :disabled="saving" @click="save()">
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
		unitType: { type: String, required: true }, // consolidator|office|counter
		parentId: { type: [String, Number, null], required: false, default: null }, // por contexto (no se pregunta)
	},
	data() {
		return {
			modal: null,
			saving: false,
			runtimeParentId: null,
			form: {
				name: '',
			},
		};
	},
	computed: {
		modalTitle() {
			if (this.unitType === 'consolidator') return 'Crear consolidator';
			if (this.unitType === 'office') return 'Crear office';
			if (this.unitType === 'counter') return 'Crear counter';
			return 'Crear unidad';
		},
	},
	methods: {
		open(payload) {
			this.reset();
			this.ensureModal();

			this.runtimeParentId =
				(payload && Object.prototype.hasOwnProperty.call(payload, 'parent_id'))
					? payload.parent_id
					: this.parentId;

			this.modal.show();
		},
		hide() {
			this.modal?.hide();
		},
		reset() {
			this.saving = false;
			this.form = { name: '' };
			this.runtimeParentId = null;
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

			let parent_id = null;

			if (this.unitType === 'consolidator') {
				parent_id = null;
			}
			else if (this.unitType === 'office') {
				// office puede ser independiente (null) o hija de consolidator (por contexto)
				parent_id = (this.runtimeParentId === null || this.runtimeParentId === '' || typeof this.runtimeParentId === 'undefined')
					? null
					: Number(this.runtimeParentId);
			}
			else if (this.unitType === 'counter') {
				// counter siempre requiere padre (por contexto)
				if (this.runtimeParentId === null || this.runtimeParentId === '' || typeof this.runtimeParentId === 'undefined') {
					window.flash('No se pudo determinar el padre para crear el counter.', 'danger');
					return;
				}
				parent_id = Number(this.runtimeParentId);
			}

			this.saving = true;
			try {
				await axios.post(this.route('admin.business-units.api.units.store'), {
					type: this.unitType,
					name,
					parent_id,
				});
				this.hide();
				this.$emit('created');
			} catch (e) {
				window.flash(this.humanError(e), 'danger');
			} finally {
				this.saving = false;
			}
		},
		humanError(e) {
			return (
				e?.response?.data?.message ||
				(e?.response?.data?.errors ? Object.values(e.response.data.errors).flat().join(' ') : null) ||
				e?.message ||
				'Error'
			);
		},
	},
};
</script>
