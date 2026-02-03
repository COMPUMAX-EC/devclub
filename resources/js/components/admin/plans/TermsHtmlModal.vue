<!-- /resources/js/components/admin/plans/TermsHtmlModal.vue -->
<template>
	<div class="modal fade" tabindex="-1" ref="modalEl">
		<div class="modal-dialog modal-fullscreen-xl-down modal-xl modal-dialog-centered">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">
						{{ modalTitle }}
					</h5>
					<button
						type="button"
						class="btn-close"
						@click="close"
						:disabled="isLoading"
					></button>
				</div>

				<div class="modal-body">
					<div v-if="isLoading" class="text-center text-muted py-5">
						Cargando términos y condiciones…
					</div>

					<div v-else>
						<InputHtml
							v-model="htmlValue"
							:id="`terms-html-${currentLocale}`"
							:name="`terms_html_${currentLocale}`"
							height="70vh"
							:debounce-ms="800"
							placeholder="Escribe aquí los términos y condiciones…"
							@update:modelValue="onHtmlUpdated"
						/>
					</div>
					<div style="text-align: right!important;" class="mt-4">
						<button
							type="button"
							class="btn btn-sm btn-secondary"
							@click="close"
							:disabled="isLoading"
						>
							Cerrar
						</button>
					</div>

				</div>

			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios'
import InputHtml from '@/components/ui/InputHtml.vue'

export default {
	name: 'AdminPlansTermsHtmlModal',

	components: {
		InputHtml,
	},

	props: {
		productId: {
			type: [Number, String],
			required: true,
		},
		planVersionId: {
			type: [Number, String],
			required: true,
		},
	},

	emits: ['updated'],

	data() {
		return {
			modalInstance: null,
			currentLocale: 'es',
			htmlValue: '',
			isLoading: false,
		}
	},

	computed: {
		modalTitle() {
			return this.currentLocale === 'en'
				? 'Términos y condiciones — Versión Inglés'
				: 'Términos y condiciones — Versión Español'
		},
		localeLabel() {
			return this.currentLocale === 'en' ? 'Inglés' : 'Español'
		},
	},

	mounted() {
		if (typeof window !== 'undefined' && window.bootstrap && this.$refs.modalEl) {
			const { Modal } = window.bootstrap
			this.modalInstance = Modal.getOrCreateInstance(this.$refs.modalEl, {
				backdrop: 'static',
			})
		}
	},

	methods: {
		open(locale = 'es') {
			this.currentLocale = locale === 'en' ? 'en' : 'es'
			this.load()

			if (this.modalInstance) {
				this.modalInstance.show()
			}
		},

		close() {
			if (this.modalInstance) {
				this.modalInstance.hide()
			}
		},

		async load() {
			this.isLoading = true
			try {
				const url = this.route('admin.products.plans.terms-html.show', {
					product: this.productId,
					planVersion: this.planVersionId,
				})

				const { data } = await axios.get(url)
				const terms = (data && data.data && data.data.terms_html) ? data.data.terms_html : {}

				const value = terms && typeof terms === 'object'
					? (terms[this.currentLocale] ?? '')
					: ''

				this.htmlValue = value || ''

				// Emitir tamaño inicial para el idioma actual
				this.$emit('updated', {
					locale: this.currentLocale,
					html: this.htmlValue,
				})
			} catch (e) {
				if (typeof flash === 'function') {
					const msg =
						e?.response?.data?.toast?.message ||
						e?.response?.data?.message ||
						'No se pudieron cargar los términos y condiciones.'
					const type = e?.response?.data?.toast?.type || 'danger'
					flash(msg, type)
				}
			} finally {
				this.isLoading = false
			}
		},

		// Se dispara después del debounce interno de <InputHtml>
		async onHtmlUpdated(newHtml) {
			this.htmlValue = newHtml || ''

			try {
				const url = this.route('admin.products.plans.terms-html.update', {
					product: this.productId,
					planVersion: this.planVersionId,
				})

				const payload = {
					locale: this.currentLocale,
					html: this.htmlValue,
				}

				const { data } = await axios.patch(url, payload)
				const toast = data?.toast

				if (toast?.message && typeof flash === 'function') {
					flash(toast.message, toast.type || 'success')
				}

				// Notificar al padre para que pueda recalcular el tamaño
				this.$emit('updated', {
					locale: this.currentLocale,
					html: this.htmlValue,
				})
			} catch (e) {
				if (typeof flash === 'function') {
					const toast = e?.response?.data?.toast
					const msg =
						toast?.message ||
						e?.response?.data?.message ||
						'No se pudo guardar los términos y condiciones.'
					const type = toast?.type || 'danger'
					flash(msg, type)
				}
			}
		},
	},
}
</script>
