<!-- /resources/js/components/admin/templates/VersionUpsertModal.vue -->
<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Editar versión</h5>

					<div class="d-flex align-items-center gap-2">
						<button
							type="button"
							v-if="editId"
							class="btn btn-sm btn-outline-primary"
							@click="openPreviewHtml"
							:disabled="isSaving || isLoading"
						>
							HTML
						</button>

						<button
							type="button"
							v-if="editId && canPdf"
							class="btn btn-sm btn-outline-primary"
							@click="openPdf"
							:disabled="isSaving || isLoading"
						>
							PDF
						</button>

						<button type="button" class="btn-close ms-2" @click="close" :disabled="isSaving || isLoading"></button>
					</div>
				</div>

				<div class="modal-body">
					<div v-if="isLoading" class="text-muted">Cargando…</div>

					<div v-else>
						<div class="d-flex align-items-center justify-content-between mb-2">
							<div style="flex: 1;">
								<label class="form-label mb-1">Nombre</label>
								<input class="form-control" v-model="form.name" @input="onInput" />
							</div>
						</div>

						<label class="form-label mb-1">Contenido (Blade HTML)</label>

						<code-editor
							language="blade"
							v-model="form.content"
							:id="'template-version-content-' + String(editId || '')"
							name="version_content"
							:debounce-ms="autosaveDelayMs"
							@update:modelValue="onContentCommit"
						/>
					</div>
				</div>

				<div class="modal-footer">
					<button class="btn btn-sm btn-secondary" @click="close" :disabled="isSaving || isLoading">Cerrar</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';

export default {
	name: 'AdminTemplatesVersionUpsertModal',

	props: {
		templateId: { type: [Number, String], required: true },
	},

	emits: ['updated', 'preview-html'],

	data() {
		return {
			modalInstance: null,
			editId: null,

			isLoading: false,
			isSaving: false,
			error: null,
			timer: null,

			// modal autónomo: decide si puede PDF consultando al servidor
			templateMeta: null, // { id, type, ... }

			form: {
				name: '',
				content: '',
			},
		};
	},

	computed: {
		canPdf() {
			return String(this.templateMeta?.type || '').toUpperCase() === 'PDF';
		},
	},

	mounted() {
		if (typeof window !== 'undefined' && window.bootstrap && this.$refs.modalEl) {
			const { Modal } = window.bootstrap;
			this.modalInstance = Modal.getOrCreateInstance(this.$refs.modalEl, { backdrop: 'static' });
		}
	},

	methods: {
		async ensureTemplateMeta(force = false) {
			if (!force && this.templateMeta) return this.templateMeta;

			const { data } = await axios.get(this.route('admin.templates.show', { template: this.templateId }));
			this.templateMeta = data?.data?.template || null;
			return this.templateMeta;
		},

		async openEdit(versionId) {
			this.editId = versionId;
			this.error = null;
			this.isLoading = true;
			this.form = { name: '', content: '' };

			if (this.timer) clearTimeout(this.timer);
			this.timer = null;

			if (this.modalInstance) this.modalInstance.show();

			try {
				await this.ensureTemplateMeta();

				const { data } = await axios.get(
					this.route('admin.templates.versions.show', { template: this.templateId, templateVersion: versionId }),
				);
				const v = data?.data?.version;
				if (v) {
					this.form.name = v.name || '';
					this.form.content = v.content || '';
				}
			} catch (e) {
				this.error = 'No se pudo cargar la versión.';
			} finally {
				this.isLoading = false;
			}
		},

		close() {
			if (this.timer) clearTimeout(this.timer);
			this.timer = null;
			if (this.modalInstance) this.modalInstance.hide();
		},

		onInput() {
			if (this.timer) clearTimeout(this.timer);
			this.timer = setTimeout(() => this.saveNow(), this.autosaveDelayMs);
		},

		onContentCommit(value) {
			if (typeof value !== 'string') return;
			this.form.content = value;
			this.onInput();
		},

		openPreviewHtml() {
			if (!this.editId) return;
			this.close();

			setTimeout(() => {
				this.$emit('preview-html', { versionId: this.editId, name: this.form.name || null });
			}, 200);
		},

		openPdf() {
			if (!this.editId) return;
			window.open(
				this.route('admin.templates.versions.preview.pdf', { template: this.templateId, templateVersion: this.editId }),
				'_blank',
			);
		},

		async saveNow() {
			if (!this.editId) return;

			this.isSaving = true;
			this.error = null;

			try {
				const payload = { name: this.form.name, content: this.form.content };
				const { data } = await axios.patch(
					this.route('admin.templates.versions.basic.update', { template: this.templateId, templateVersion: this.editId }),
					payload,
				);

				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success');
				}

				// Regla: NO usar respuesta para pisar/actualizar valores locales.
				// Backend ya no devuelve modelo.
				this.$emit('updated', { id: this.editId });
			} catch (e) {
				this.error =
					e?.response?.data?.message ||
					e?.response?.data?.toast?.message ||
					'No se pudo guardar la versión.';

				// Regla: NO pisar lo que el usuario tiene con valores del backend.
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
