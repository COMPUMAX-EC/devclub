<!-- /resources/js/components/admin/templates/TemplateUpsertModal.vue -->
<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-dialog-centered modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">{{ mode === 'create' ? 'Crear plantilla' : 'Editar plantilla' }}</h5>
					<button type="button" class="btn-close" @click="close" :disabled="isSaving || isLoading"></button>
				</div>

				<div class="modal-body">
					<div v-if="isLoading" class="text-muted">Cargando…</div>

					<div v-else>
						<div class="row g-2">
							<div class="col-md-6">
								<label class="form-label">Nombre</label>
								<input class="form-control" v-model="form.name" @input="onInput" />
							</div>

							<div class="col-md-6">
								<label class="form-label">Slug</label>
								<input class="form-control" v-model="form.slug" @input="onInput" />
							</div>

							<div class="col-md-6" v-if="mode === 'create'">
								<label class="form-label">Type</label>
								<select class="form-select" v-model="form.type" @change="onInput">
									<option value="HTML">HTML</option>
									<option value="PDF">PDF</option>
								</select>
								<div class="form-text">No editable luego desde el front.</div>
							</div>
						</div>

						<div v-if="error" class="alert alert-danger py-2 small mt-3 mb-0">{{ error }}</div>
					</div>
				</div>

				<div class="modal-footer">
					<button class="btn btn-sm btn-secondary" @click="close" :disabled="isSaving || isLoading">Cerrar</button>
					<button v-if="mode=='create'" class="btn btn-sm btn-primary" @click="saveNow" :disabled="isSaving || isLoading">Guardar</button>
					<div class="text-muted small ms-2" v-if="isSaving">Guardando…</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios'

export default {
	name: 'AdminTemplatesTemplateUpsertModal',

	emits: ['created', 'updated'],

	data() {
		return {
			modalInstance: null,
			mode: 'create', // create | edit
			editId: null,

			isLoading: false,
			isSaving: false,
			error: null,
			timer: null,

			form: {
				name: '',
				slug: '',
				type: 'HTML',
			},
		}
	},

	mounted() {
		if (typeof window !== 'undefined' && window.bootstrap && this.$refs.modalEl) {
			const { Modal } = window.bootstrap
			this.modalInstance = Modal.getOrCreateInstance(this.$refs.modalEl, { backdrop: 'static' })
		}
	},

	methods: {
		openCreate() {
			this.mode = 'create'
			this.editId = null
			this.error = null
			this.isLoading = false
			this.form = { name: '', slug: '', type: 'HTML' }
			if (this.modalInstance) this.modalInstance.show()
		},

		async openEdit(templateId) {
			this.mode = 'edit'
			this.editId = templateId
			this.error = null
			this.isLoading = true
			this.form = { name: '', slug: '', type: 'HTML' }
			if (this.modalInstance) this.modalInstance.show()

			try {
				const { data } = await axios.get(this.route('admin.templates.show', { template: templateId }))
				const t = data?.data?.template
				if (t) {
					this.form.name = t.name || ''
					this.form.slug = t.slug || ''
					// type no editable por front
				}
			} catch (e) {
				this.error = 'No se pudo cargar la plantilla.'
			} finally {
				this.isLoading = false
			}
		},

		close() {
			if (this.modalInstance) this.modalInstance.hide()
		},

		onInput() {
			if (this.mode !== 'edit') return
			if (this.timer) clearTimeout(this.timer)
			this.timer = setTimeout(() => this.saveNow(), this.autosaveDelayMs)
		},

		async saveNow() {
			this.isSaving = true
			this.error = null

			try {
				if (this.mode === 'create') {
					const payload = { name: this.form.name, slug: this.form.slug, type: this.form.type }
					const { data } = await axios.post(this.route('admin.templates.store'), payload)

					if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
						window.flash(data.toast.message, data.toast.type || 'success')
					}

					// create sí devuelve template (se usa para insertar en Index)
					this.$emit('created', { template: data?.data?.template })
					this.close()
					return
				}

				const payload = { name: this.form.name, slug: this.form.slug }
				const { data } = await axios.patch(
					this.route('admin.templates.basic.update', { template: this.editId }),
					payload
				)

				if (typeof window !== 'undefined' && typeof window.flash === 'function' && data?.toast?.message) {
					window.flash(data.toast.message, data.toast.type || 'success')
				}

				// Regla: NO reemplazar con respuesta del backend (y backend ya no devuelve modelo)
				this.$emit('updated', { id: this.editId })
			} catch (e) {
				this.error = e?.response?.data?.message || e?.response?.data?.toast?.message || 'No se pudo guardar.'

				// Regla: NO pisar lo que el usuario tiene con valores del backend.
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(this.error, 'danger')
				}
			} finally {
				this.isSaving = false
			}
		},
	},
}
</script>
