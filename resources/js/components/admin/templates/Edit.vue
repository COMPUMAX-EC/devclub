<!-- /resources/js/components/admin/templates/Edit.vue -->
<template>
	<div>
		<div class="d-flex align-items-center justify-content-between mb-3">
			<div>
				<div class="text-muted small" v-if="template">
					<code>Slug: <b>{{ template.slug }}</b></code>
					<code>Tipo: <b>{{ template.type }}</b></code>
				</div>
			</div>

			<div class="d-flex gap-2">
				<button class="btn btn-sm btn-outline-secondary" @click="openEditBasic" :disabled="isLoading || !template">
					Editar básicos
				</button>
				<button class="btn btn-sm btn-outline-secondary" @click="openEditTemplateTestData" :disabled="isLoading || !template">
					Editar JSON
				</button>

				<div class="btn-group">
					<button class="btn btn-sm btn-outline-primary" @click="openActiveHtml" :disabled="!hasActiveVersion">
						HTML
					</button>
					<button v-if="isTypePdf" class="btn btn-sm btn-outline-primary" @click="openActivePdf" :disabled="!hasActiveVersion">
						PDF
					</button>
				</div>
			</div>
		</div>

		<div class="card mb-3">
			<div class="card-body">
				<div class="d-flex align-items-center justify-content-between">
					<div>
						<div class="fw-semibold">Versiones</div>
					</div>
					<button
						class="btn btn-sm btn-primary"
						@click="createVersionConfirm"
						:disabled="isLoading || !template || isCreatingVersion"
					>
						Crear versión
					</button>
				</div>
			</div>

			<div class="">
				<table class="table table-striped table-condensed table-hover mb-0">
					<thead>
						<tr>
							<th style="width: 70px">ID</th>
							<th>Nombre</th>
							<th style="width: 90px">Activa</th>
							<th style="width: 330px" class="text-end">Acciones</th>
						</tr>
					</thead>
					<tbody>
						<tr v-if="versions.length === 0">
							<td colspan="4" class="text-center text-muted py-4">Sin versiones</td>
						</tr>

						<tr v-for="v in versions" :key="v.id">
							<td>{{ v.id }}</td>
							<td>{{ v.name }}</td>
							<td>
								<span v-if="isActiveVersion(v)" class="badge bg-success">Sí</span>
								<span v-else class="badge bg-secondary">No</span>
							</td>

							<td class="text-end">
								<div class="btn-group" role="group" aria-label="Acciones versión">
									<button
										type="button"
										class="btn btn-sm btn-light-primary"
										@click="openEditVersion(v)"
										:disabled="isLoading"
									>
										Editar
									</button>
									<button
										v-if="isTypePdf"
										type="button"
										class="btn btn-sm btn-light-danger"
										@click.prevent="openVersionPdf(v)"
									>
										PDF
									</button>

									<div class="btn-group" role="group">
										<button
											type="button"
											class="btn btn-sm btn-secondary dropdown-toggle dropdown-toggle-split"
											data-bs-toggle="dropdown"
											aria-expanded="false"
											:disabled="isBusyVersionId === v.id"
										>
											<span class="visually-hidden">Abrir opciones</span>
										</button>

										<ul class="dropdown-menu dropdown-menu-end">
											<li>
												<a class="dropdown-item" href="#" @click.prevent="openEditVersionTestData(v)">JSON</a>
											</li>
											<li>
												<a class="dropdown-item" href="#" @click.prevent="cloneVersion(v)">Clonar</a>
											</li>

											<li><hr class="dropdown-divider" /></li>

											<li v-if="!isActiveVersion(v)">
												<a class="dropdown-item" href="#" @click.prevent="activate(v)">Activar</a>
											</li>
											<li v-else>
												<a class="dropdown-item" href="#" @click.prevent="deactivate(v)">Desactivar</a>
											</li>

											<li v-if="!isActiveVersion(v)"><hr class="dropdown-divider" /></li>
											<li v-if="!isActiveVersion(v)">
												<a
													class="dropdown-item text-danger"
													href="#"
													@click.prevent="deleteVersion(v)"
													:title="isActiveVersion(v) ? 'No se puede eliminar una versión activa' : 'Eliminar versión'"
												>
													Eliminar
												</a>
											</li>
										</ul>
									</div>
								</div>
							</td>

						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<admin-templates-template-upsert-modal
			ref="templateUpsertModal"
			@updated="onTemplateChanged"
		/>

		<admin-templates-template-test-data-modal
			ref="templateTestDataModal"
			@updated="onTemplateChanged"
		/>

		<admin-templates-version-upsert-modal
			ref="versionUpsertModal"
			:template-id="templateId"
			@updated="onVersionChanged"
			@preview-html="onPreviewHtmlRequested"
		/>

		<admin-templates-version-test-data-modal
			ref="versionTestDataModal"
			:template-id="templateId"
			@updated="onVersionChanged"
		/>

		<admin-templates-preview-html-modal
			ref="previewHtmlModal"
			:template-id="templateId"
			@edit-version="onEditVersionRequested"
		/>
	</div>
</template>

<script>
import axios from 'axios'

export default {
	name: 'AdminTemplatesEdit',

	props: {
		templateId: { type: [Number, String], required: true },
	},

	data() {
		return {
			isLoading: false,
			isCreatingVersion: false,
			template: null,
			versions: [],
			isBusyVersionId: null,
		}
	},

	computed: {
		hasActiveVersion() {
			return !!this.template?.active_template_version_id
		},
		activeVersionId() {
			return this.template?.active_template_version_id || null
		},
		isTypePdf() {
			return String(this.template?.type || '').toUpperCase() === 'PDF'
		},
	},

	mounted() {
		this.refresh()
	},

	methods: {
		isActiveVersion(v) {
			return !!v?.id && String(v.id) === String(this.activeVersionId)
		},

		sortVersionsAsc() {
			this.versions = [...this.versions].sort((a, b) => (a.id || 0) - (b.id || 0))
		},

		async refresh() {
			this.isLoading = true
			try {
				const { data } = await axios.get(this.route('admin.templates.show', { template: this.templateId }))
				this.template = data?.data?.template || this.template
				this.versions = Array.isArray(data?.data?.versions) ? data.data.versions : this.versions
				this.sortVersionsAsc()
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash('No se pudo cargar la plantilla.', 'danger')
				}
			} finally {
				this.isLoading = false
			}
		},

		openEditBasic() {
			this.$refs.templateUpsertModal?.openEdit(this.templateId)
		},

		openEditTemplateTestData() {
			this.$refs.templateTestDataModal?.open(this.templateId)
		},

		async createVersionConfirm() {
			if (!confirm('¿Crear una versión?')) return

			this.isCreatingVersion = true
			try {
				const { data } = await axios.post(this.route('admin.templates.versions.store', { template: this.templateId }), {})

				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success')
				}

				await this.refresh()
			} catch (e) {
				const msg = e?.response?.data?.message || e?.response?.data?.toast?.message || 'No se pudo crear la versión.'
				if (typeof window !== 'undefined' && typeof window.flash === 'function') window.flash(msg, 'danger')
			} finally {
				this.isCreatingVersion = false
			}
		},

		openEditVersion(v) {
			this.$refs.versionUpsertModal?.openEdit(v?.id)
		},

		openEditVersionTestData(v) {
			this.$refs.versionTestDataModal?.open(v?.id)
		},

		// IMPORTANTE:
		// No refrescar automáticamente al guardar por debounce.
		// Eso re-renderiza y hace perder foco en el editor.
		onTemplateChanged() {
			// noop
		},

		onVersionChanged() {
			// noop
		},

		// Desde VersionUpsertModal -> abrir preview HTML y RAW toggle
		onPreviewHtmlRequested(payload) {
			const versionId = payload?.versionId
			if (!versionId) return
			this.$refs.previewHtmlModal?.openVersion({
				versionId,
				name: payload?.name || null,
			})
		},

		// Desde PreviewHtmlModal -> abrir modal de edición de versión
		onEditVersionRequested(payload) {
			const versionId = payload?.versionId
			if (!versionId) return
			this.$refs.versionUpsertModal?.openEdit(versionId)
		},

		// Preview activa: abre modal (HTML render / RAW toggle)
		openActiveHtml() {
			if (!this.activeVersionId) return
			this.$refs.previewHtmlModal?.openActive({
				slug: this.template?.slug || null,
				versionId: this.activeVersionId,
			})
		},

		// PDF activo sigue en nueva pestaña
		openActivePdf() {
			if (!this.activeVersionId) return
			window.open(this.route('admin.templates.active.preview.pdf', { template: this.templateId }), '_blank')
		},

		// Preview por versión: abre modal
		openVersionHtml(v) {
			if (!v?.id) return
			this.$refs.previewHtmlModal?.openVersion({
				versionId: v.id,
				name: v?.name || null,
			})
		},

		openVersionPdf(v) {
			if (!v?.id) return
			window.open(
				this.route('admin.templates.versions.preview.pdf', { template: this.templateId, templateVersion: v.id }),
				'_blank'
			)
		},

		async cloneVersion(v) {
			if (!v?.id) return
			this.isBusyVersionId = v.id
			try {
				const { data } = await axios.post(
					this.route('admin.templates.versions.clone', { template: this.templateId, templateVersion: v.id })
				)
				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success')
				}
				await this.refresh()
			} catch (e) {
				const msg = e?.response?.data?.message || e?.response?.data?.toast?.message || 'No se pudo clonar la versión.'
				if (typeof window !== 'undefined' && typeof window.flash === 'function') window.flash(msg, 'danger')
			} finally {
				this.isBusyVersionId = null
			}
		},

		async activate(v) {
			if (!v?.id) return
			this.isBusyVersionId = v.id
			try {
				const { data } = await axios.post(
					this.route('admin.templates.versions.activate', { template: this.templateId, templateVersion: v.id })
				)
				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success')
				}
				await this.refresh()
			} catch (e) {
				const msg = e?.response?.data?.message || e?.response?.data?.toast?.message || 'No se pudo activar la versión.'
				if (typeof window !== 'undefined' && typeof window.flash === 'function') window.flash(msg, 'danger')
			} finally {
				this.isBusyVersionId = null
			}
		},

		async deactivate(v) {
			if (!v?.id) return
			this.isBusyVersionId = v.id
			try {
				const { data } = await axios.post(
					this.route('admin.templates.versions.deactivate', { template: this.templateId, templateVersion: v.id })
				)
				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success')
				}
				await this.refresh()
			} catch (e) {
				const msg = e?.response?.data?.message || e?.response?.data?.toast?.message || 'No se pudo desactivar la versión.'
				if (typeof window !== 'undefined' && typeof window.flash === 'function') window.flash(msg, 'danger')
			} finally {
				this.isBusyVersionId = null
			}
		},

		async deleteVersion(v) {
			if (!v?.id) return
			if (this.isActiveVersion(v)) return
			if (!confirm('¿Eliminar versión?')) return

			this.isBusyVersionId = v.id
			try {
				const { data } = await axios.delete(
					this.route('admin.templates.versions.destroy', { template: this.templateId, templateVersion: v.id })
				)
				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success')
				}
				await this.refresh()
			} catch (e) {
				const msg = e?.response?.data?.message || e?.response?.data?.toast?.message || 'No se pudo eliminar la versión.'
				if (typeof window !== 'undefined' && typeof window.flash === 'function') window.flash(msg, 'danger')
			} finally {
				this.isBusyVersionId = null
			}
		},
	},
}
</script>
