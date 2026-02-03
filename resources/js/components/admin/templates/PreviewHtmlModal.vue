<!-- /resources/js/components/admin/templates/PreviewHtmlModal.vue -->
<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">{{ title }}</h5>

					<div class="d-flex align-items-center gap-2">
						<button
							type="button"
							class="btn btn-sm btn-outline-secondary"
							@click="openEditVersion"
							:disabled="isLoading || !context.versionId"
						>
							Editar
						</button>

						<button
							v-if="showPdfButton"
							type="button"
							class="btn btn-sm btn-outline-primary"
							@click="openPdf"
							:disabled="isLoading || !context.versionId"
						>
							PDF
						</button>

						<button type="button" class="btn-close" @click="close"></button>
					</div>
				</div>

				<div v-if="isLoading" class="modal-body">Cargando…</div>

				<div style="position: relative;" v-else>
					<div v-if="error" class="alert alert-danger py-2 small mb-3">{{ error }}</div>

					<div v-if="!showRaw">
						<div class="border p-3" style="min-height: 50vh;">
							<div v-html="html"></div>
						</div>
					</div>

					<div v-else>
						<code-editor
							language="html"
							:readonly="true"
							v-model="html"
							style="min-height: 50vh; border-radius: 0px;"
						/>
					</div>

					<button
						type="button"
						class="btn btn-sm btn-secondary"
						style="position: absolute; top: 0px; right: 0px;"
						@click="toggleRaw"
						:disabled="isLoading || !html"
					>
						{{ showRaw ? 'Ver HTML' : 'Ver RAW' }}
					</button>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-sm btn-secondary" @click="close">Cerrar</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';

export default {
	name: 'AdminTemplatesPreviewHtmlModal',

	props: {
		templateId: { type: [Number, String], required: true },
	},

	emits: ['edit-version'],

	data() {
		return {
			modalInstance: null,

			isLoading: false,
			error: null,

			html: '',
			showRaw: false,

			// meta del template (modal autónomo, vía AJAX)
			templateMeta: null, // { id, slug, type, active_template_version_id, ... }

			context: {
				mode: 'version', // solo 'version'
				versionId: null,
				name: null,
			},
		};
	},

	computed: {
		isTypePdf() {
			return String(this.templateMeta?.type || '').toUpperCase() === 'PDF';
		},

		showPdfButton() {
			return this.isTypePdf && !!this.context?.versionId;
		},

		title() {
			const id = this.context.versionId ? `#${this.context.versionId}` : '';
			const name = this.context.name ? `— ${this.context.name}` : '';
			return `Preview HTML Versión ${id} ${name}`.trim();
		},
	},

	mounted() {
		if (typeof window !== 'undefined' && window.bootstrap && this.$refs.modalEl) {
			const { Modal } = window.bootstrap;
			this.modalInstance = Modal.getOrCreateInstance(this.$refs.modalEl, { backdrop: 'static' });
		}
	},

	methods: {
		show() {
			if (this.modalInstance) this.modalInstance.show();
		},

		close() {
			if (this.modalInstance) this.modalInstance.hide();
		},

		toggleRaw() {
			this.showRaw = !this.showRaw;
		},

		openEditVersion() {
			if (!this.context.versionId) return;
			this.close();

			setTimeout(() => {
				this.$emit('edit-version', { versionId: this.context.versionId });
			}, 200);
		},

		async ensureTemplateMeta(force = false) {
			if (!force && this.templateMeta) return this.templateMeta;

			const { data } = await axios.get(this.route('admin.templates.show', { template: this.templateId }));
			this.templateMeta = data?.data?.template || null;
			return this.templateMeta;
		},

		/**
		 * meta: { versionId, name? }
		 */
		async openVersion(meta = {}) {
			this.isLoading = true;
			this.error = null;
			this.html = '';
			this.showRaw = false;

			try {
				await this.ensureTemplateMeta();

				const versionId = meta?.versionId ?? null;
				if (!versionId) {
					this.error = 'Falta versionId.';
					this.show();
					return;
				}

				let name = meta?.name ?? null;
				if (!name) {
					const { data } = await axios.get(
						this.route('admin.templates.versions.show', { template: this.templateId, templateVersion: versionId }),
					);
					name = data?.data?.version?.name ?? null;
				}

				this.context = {
					mode: 'version',
					versionId,
					name,
				};

				await this.fetchHtml(
					this.route('admin.templates.versions.preview.raw', {
						template: this.templateId,
						templateVersion: versionId,
					}),
				);

				this.show();
			} catch (e) {
				this.error = 'No se pudo cargar la versión.';
				this.show();
			} finally {
				this.isLoading = false;
			}
		},

		openPdf() {
			if (!this.isTypePdf) return;
			if (!this.context.versionId) return;

			window.open(
				this.route('admin.templates.versions.preview.pdf', {
					template: this.templateId,
					templateVersion: this.context.versionId,
				}),
				'_blank',
			);
		},

		async fetchHtml(url) {
			this.error = null;
			this.html = '';

			try {
				const res = await axios.get(url, { responseType: 'text' });
				this.html = typeof res?.data === 'string' ? res.data : '';
				if (!this.html) this.error = 'No se recibió contenido HTML.';
			} catch (e) {
				this.error = 'No se pudo cargar el HTML.';
			}
		},
	},
};
</script>
