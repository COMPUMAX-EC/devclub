<!-- /resources/js/components/admin/templates/Index.vue -->
<template>
	<div>
		<div class="d-flex align-items-center justify-content-between mb-3">
			<h1 class="h4 mb-0">Plantillas</h1>
			<button class="btn btn-sm btn-primary" @click="openCreate" :disabled="isLoading">
				Crear plantilla
			</button>
		</div>

		<div class="card">
			<div class="card-body p-0">
				<div v-if="isLoading" class="p-3 text-muted">Cargando…</div>

				<div v-else class="">
					<table class="table table-striped table-condensed table-hover mb-0">
						<thead>
							<tr>
								<th style="width: 70px">ID</th>
								<th>Nombre</th>
								<th>Slug</th>
								<th style="width: 90px">Type</th>
								<th>Versión activa</th>
								<th style="width: 220px" class="text-end">Acciones</th>
							</tr>
						</thead>

						<tbody>
							<tr v-if="templates.length === 0">
								<td colspan="6" class="text-center text-muted py-4">Sin plantillas</td>
							</tr>

							<tr v-for="t in templates" :key="t.id">
								<td>{{ t.id }}</td>
								<td>{{ t.name }}</td>
								<td><code>{{ t.slug }}</code></td>
								<td><span class="badge bg-secondary">{{ t.type }}</span></td>

								<td>
									<div v-if="t?.active_template_version_id">
										<span class="badge bg-success"></span>
										<span class="">
											{{ t?.active_version?.name || '—' }}
										</span>
									</div>
									<span v-else class="text-muted">—</span>
								</td>

								<td class="text-end">
									<div class="btn-group" role="group" aria-label="Acciones plantilla">
										<a
											class="btn btn-sm btn-light-primary"
											:href="route('admin.templates.edit', { template: t.id })"
										>
											Administrar
										</a>

										<button
											type="button"
											class="btn btn-sm btn-secondary dropdown-toggle dropdown-toggle-split"
											data-bs-toggle="dropdown"
											aria-expanded="false"
											:disabled="isBusyId === t.id"
										>
											<span class="visually-hidden">Abrir opciones</span>
										</button>

										<ul class="dropdown-menu dropdown-menu-end">
											<li>
												<a
													class="dropdown-item"
													href="#"
													@click.prevent="openActiveHtml(t)"
													v-if="t?.active_template_version_id"
													:title="t?.active_template_version_id ? 'Preview HTML de la versión activa' : 'No hay versión activa'"
												>
													Preview HTML
												</a>
											</li>

											<li v-if="isPdfType(t)">
												<a
													class="dropdown-item"
													href="#"
													@click.prevent="openActivePdf(t)"
													v-if="t?.active_template_version_id"
													:title="t?.active_template_version_id ? 'Abrir PDF de la versión activa' : 'No hay versión activa'"
												>
													Preview PDF
												</a>
											</li>

											<li>
												<a
													class="dropdown-item"
													href="#"
													@click.prevent="cloneTemplate(t)"
													:class="{ disabled: isBusyId === t.id }"
												>
													Clonar
												</a>
											</li>

											<li><hr class="dropdown-divider" /></li>

											<li>
												<a
													class="dropdown-item text-danger"
													href="#"
													@click.prevent="deleteTemplate(t)"
													:class="{ disabled: isBusyId === t.id }"
												>
													Eliminar
												</a>
											</li>
										</ul>
									</div>
								</td>
							</tr>

						</tbody>
					</table>
				</div>

			</div>
		</div>

		<admin-templates-template-upsert-modal
			ref="templateUpsertModal"
			@created="onCreated"
		/>

		<!-- Modal único: se “retargetea” cambiando el templateId -->
		<admin-templates-preview-html-modal
			v-if="previewTemplateId"
			:key="'preview-' + String(previewTemplateId)"
			ref="previewHtmlModal"
			:template-id="previewTemplateId"
		/>
	</div>
</template>

<script>
import axios from 'axios'

export default {
	name: 'AdminTemplatesIndex',

	props: {
		initialTemplates: { type: Array, default: () => [] },
	},

	data() {
		return {
			templates: Array.isArray(this.initialTemplates) ? [...this.initialTemplates] : [],
			isLoading: false,
			isBusyId: null,

			previewTemplateId: null,
		}
	},

	mounted() {
		// data fresca (mismo endpoint index devuelve JSON en AJAX)
		this.refresh()
	},

	methods: {
		async refresh() {
			this.isLoading = true
			try {
				const { data } = await axios.get(this.route('admin.templates.index'))
				this.templates = Array.isArray(data?.data) ? data.data : this.templates
			} catch (e) {
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash('No se pudo cargar la lista de plantillas.', 'danger')
				}
			} finally {
				this.isLoading = false
			}
		},

		openCreate() {
			this.$refs.templateUpsertModal?.openCreate()
		},

		onCreated(payload) {
			if (payload?.template) {
				this.templates.unshift(payload.template)
				return
			}
			this.refresh()
		},

		isPdfType(t) {
			return String(t?.type || '').toUpperCase() === 'PDF'
		},

		async openActiveHtml(t) {
			if (!t?.id) return
			if (!t?.active_template_version_id) return

			this.previewTemplateId = t.id
			await this.$nextTick()

			this.$refs.previewHtmlModal?.openVersion({
				versionId: t.active_template_version_id,
				name: t?.active_version?.name || null,
			})
		},

		openActivePdf(t) {
			if (!t?.id) return
			if (!this.isPdfType(t)) return
			if (!t?.active_template_version_id) return

			// PDF por versión
			window.open(
				this.route('admin.templates.versions.preview.pdf', {
					template: t.id,
					templateVersion: t.active_template_version_id,
				}),
				'_blank'
			)
		},

		async cloneTemplate(t) {
			if (!t?.id) return
			this.isBusyId = t.id
			try {
				const { data } = await axios.post(this.route('admin.templates.clone', { template: t.id }))
				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success')
				}
				await this.refresh()
			} catch (e) {
				const msg = e?.response?.data?.message || e?.response?.data?.toast?.message || 'No se pudo clonar la plantilla.'
				if (typeof window !== 'undefined' && typeof window.flash === 'function') window.flash(msg, 'danger')
			} finally {
				this.isBusyId = null
			}
		},

		async deleteTemplate(t) {
			if (!t?.id) return
			if (!confirm('¿Eliminar plantilla? (soft delete)')) return

			this.isBusyId = t.id
			try {
				const { data } = await axios.delete(this.route('admin.templates.destroy', { template: t.id }))
				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success')
				}
				await this.refresh()
			} catch (e) {
				const msg = e?.response?.data?.message || e?.response?.data?.toast?.message || 'No se pudo eliminar la plantilla.'
				if (typeof window !== 'undefined' && typeof window.flash === 'function') window.flash(msg, 'danger')
			} finally {
				this.isBusyId = null
			}
		},
	},
}
</script>
