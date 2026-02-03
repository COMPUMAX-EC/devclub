<!-- /resources/js/components/admin/templates/VersionTestDataModal.vue -->
<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Editar JSON de datos de prueba (Versión)</h5>
					<button type="button" class="btn-close"></button>
				</div>

				<div class="modal-body">
					<div v-if="isLoading" class="text-muted">Cargando…</div>

					<div v-else>
						<code-editor
							language="json"
							v-model="rawJson"
							:id="'version-test-data-' + String(versionId || '')"
							name="version_test_data_json"
							:debounce-ms="autosaveDelayMs"
							@update:modelValue="onCommit"
						/>
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-secondary" @click="close" :disabled="isSaving || isLoading">Cerrar</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';

export default {
	name: 'AdminTemplatesVersionTestDataModal',

	props: {
		templateId: { type: [Number, String], required: true },
	},

	emits: ['updated'],

	data() {
		return {
			modalInstance: null,
			versionId: null,

			isLoading: false,
			isSaving: false,
			error: null,

			rawJson: '',

			// control de autosave por inactividad solo si hubo cambios reales
			lastSavedValue: '',
		};
	},

	mounted() {
		if (typeof window !== 'undefined' && window.bootstrap && this.$refs.modalEl) {
			const { Modal } = window.bootstrap;
			this.modalInstance = Modal.getOrCreateInstance(this.$refs.modalEl, { backdrop: 'static' });
		}
	},

	methods: {
		async open(versionId) {
			this.versionId = versionId;
			this.error = null;
			this.isLoading = true;
			this.rawJson = '';
			this.lastSavedValue = '';

			if (this.modalInstance) this.modalInstance.show();

			try {
				const { data } = await axios.get(
					this.route('admin.templates.versions.show', { template: this.templateId, templateVersion: versionId }),
				);
				const v = data?.data?.version;
				this.rawJson = v?.test_data_json || '';
				this.lastSavedValue = this.rawJson;
			} catch (e) {
				this.error = 'No se pudo cargar el JSON.';
			} finally {
				this.isLoading = false;
			}
		},

		close() {
			if (this.modalInstance) this.modalInstance.hide();
		},

		onCommit(value) {
			if (typeof value !== 'string') return;
			if (value === this.lastSavedValue) return;

			this.rawJson = value;
			this.save();
		},

		async save() {
			if (!this.versionId) return;
			if (this.rawJson === this.lastSavedValue) return;

			this.isSaving = true;
			this.error = null;

			try {
				const payload = { test_data_json: this.rawJson };
				const { data } = await axios.patch(
					this.route('admin.templates.versions.test-data.update', { template: this.templateId, templateVersion: this.versionId }),
					payload,
				);

				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success');
				}

				// Regla: nunca reemplazar el valor local con lo que responda el backend.
				// El backend ya no devuelve version en edición.
				this.lastSavedValue = this.rawJson;
				this.$emit('updated', { id: this.versionId });
			} catch (e) {
				this.error =
					e?.response?.data?.message ||
					e?.response?.data?.toast?.message ||
					'No se pudo guardar el JSON.';

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(this.error, 'danger');
				}
			} finally {
				this.isSaving = false;
			}
		},
	},
};
</script>
