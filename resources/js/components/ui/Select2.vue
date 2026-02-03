<!-- resources/js/components/ui/Select2.vue -->
<template>
	<select
		ref="select"
		class="form-select"
		:id="id"
		:name="name"
		:disabled="disabled"
		:data-placeholder="placeholder"
	>
		<!-- Placeholder siempre disponible (empty on init) -->

		<!--
			MODO PLACEHOLDER (clásico):
			- Para que Select2 muestre placeholder, debe existir una opción vacía value=""
			- Esta opción NO lleva texto (Select2 usa config.placeholder para el texto).
		-->
		<option
			v-if="placeholder"
			value=""
		></option>

		<!--
			MODO nullable = string:
			- El string es el texto de una opción "limpiar / dejar en blanco".
			- Importante: esta opción NO debe reemplazar el placeholder.
			- Al seleccionarla, emitimos null y forzamos el select a quedar vacío ("") para que se vea el placeholder.
			- Usamos sentinel (no vacío) porque Select2/DOM tienen casos raros con value="" como opción seleccionada.
		-->
		<option
			v-if="nullableNullOptionText  && modelValue!=null"
			:value="nullSentinel"
		>
			{{ nullableNullOptionText }}
		</option>

		<!-- Con grupos -->
		<template v-if="hasGroups">
			<optgroup
				v-for="group in groupedOptions"
				:key="group.key"
				:label="group.label"
			>
				<option
					v-for="opt in group.options"
					:key="opt.value"
					:value="opt.value"
				>
					{{ opt.label }}
				</option>
			</optgroup>
		</template>

		<!-- Sin grupos -->
		<template v-else>
			<option
				v-for="opt in flatOptions"
				:key="opt.value"
				:value="opt.value"
			>
				{{ opt.label }}
			</option>
		</template>
	</select>
</template>

<script>
export default {
	name: 'Select2',

	props: {
		modelValue: {
			type: [String, Number, null],
			default: null,
		},

		/**
		 * Lista de opciones:
		 * - Caso A (recomendado): Array de objetos "tal como viene de BD".
		 *   Se usan value-field, label-field y group-field para leer columnas.
		 *
		 * - Caso B (solo si NO hay group-field): mapa simple value => label,
		 *   por ejemplo: { "CL": "Chile", "AR": "Argentina", ... }.
		 */
		options: {
			type: [Array, Object],
			required: true,
		},

		// Nombre del campo que contiene el valor
		valueField: {
			type: String,
			default: 'id',
		},

		// Nombre del campo que contiene la etiqueta visible
		labelField: {
			type: String,
			default: 'name',
		},

		// Nombre del campo que contiene el grupo (opcional)
		groupField: {
			type: String,
			default: null,
		},

		placeholder: {
			type: String,
			default: '',
		},

		/**
		 * nullable soporta:
		 * - false: NO permitir volver a null desde UI
		 * - true: permitir null (placeholder clásico)
		 * - string: implica nullable=true y además crea una opción "limpiar" visible al inicio
		 *           con ese texto; esa opción emite null y vuelve a mostrar el placeholder.
		 */
		nullable: {
			// eslint-disable-next-line vue/require-prop-types
			type: [Boolean, String],
			default: true,
		},

		// Habilitar/deshabilitar el buscador de Select2
		searchEnabled: {
			type: Boolean,
			default: true,
		},

		// Búsqueda insensible a mayúsculas y tildes
		accentInsensitive: {
			type: Boolean,
			default: true,
		},

		disabled: {
			type: Boolean,
			default: false,
		},

		id: {
			type: String,
			default: null,
		},

		name: {
			type: String,
			default: null,
		},
	},

	emits: ['update:modelValue', 'select2-change'],

	data() {
		return {
			internalValue: this.modelValue ?? null,
			lastEmittedValue: this.modelValue ?? null,
			isSyncingFromModel: false,

			// Evita doble-emisión cuando interceptamos la opción sentinel y luego disparamos un change programático.
			isHandlingSentinelClear: false,
		}
	},

	computed: {
		// Sentinel para representar "acción de limpiar" (no vacío) y no colisionar con valores reales.
		nullSentinel() {
			const base = this.id || this.name || 'select2'
			return `__NULL__:${base}`
		},

		// Normaliza "nullable" a booleans según la convención:
		// - false => no nullable
		// - true => nullable
		// - string => nullable
		isNullable() {
			return this.nullable !== false
		},

		// Si nullable es string, este será el texto de la opción "limpiar"
		nullableNullOptionText() {
			if (typeof this.nullable === 'string') {
				const t = this.nullable.trim()
				return t !== '' ? t : null
			}
			return null
		},

		/**
		 * Opciones normalizadas => siempre { value, label, group, searchText }
		 * searchText concatena todas las variantes de label (multi-idioma) +,
		 * opcionalmente, el texto de grupo (también multi-idioma si aplica).
		 */
		normalizedOptions() {
			const src = this.options || []

			// Caso B: mapa value => label (solo si NO hay groupField)
			if (!this.groupField && !Array.isArray(src) && typeof src === 'object') {
				return Object.entries(src).map(([value, label]) => {
					const labelStrings = this.collectLabelStrings(label)
					const searchText =
						labelStrings.length > 0
							? labelStrings.join(' ')
							: (label != null ? String(label) : '')

					return {
						value,
						label: this.applyTranslate(label),
						group: null,
						searchText,
					}
				})
			}

			// Caso A: array de filas tal como viene de BD
			if (!Array.isArray(src)) {
				return []
			}

			return src.map(item => {
				const rawValue = item?.[this.valueField]
				const rawLabel = item?.[this.labelField]
				const rawGroup = this.groupField ? item?.[this.groupField] : null

				const labelStrings = this.collectLabelStrings(rawLabel)
				const groupStrings = this.collectLabelStrings(rawGroup)
				const allStrings = [...labelStrings, ...groupStrings]

				const searchText =
					allStrings.length > 0
						? allStrings.join(' ')
						: (
							rawLabel != null
								? String(rawLabel)
								: (rawGroup != null ? String(rawGroup) : '')
						)

				return {
					value: rawValue,
					label: this.applyTranslate(rawLabel),
					group: rawGroup != null ? this.applyTranslate(rawGroup) : null,
					searchText,
				}
			})
		},

		hasGroups() {
			return !!this.groupField && this.normalizedOptions.some(o => o.group)
		},

		flatOptions() {
			if (this.hasGroups) {
				return []
			}
			return this.normalizedOptions
		},

		groupedOptions() {
			if (!this.hasGroups) {
				return []
			}

			const groups = {}

			this.normalizedOptions.forEach(opt => {
				const key = opt.group || ''
				if (!groups[key]) {
					groups[key] = {
						key,
						label: opt.group || '',
						options: [],
					}
				}
				groups[key].options.push(opt)
			})

			return Object.values(groups)
		},
	},

	watch: {
		modelValue(newVal) {
			this.internalValue = newVal ?? null
			this.lastEmittedValue = newVal ?? null
			this.$nextTick(() => {
				this.syncFromModel()
			})
		},

		options: {
			handler() {
				// Re-render DOM y re-inicializar Select2
				this.$nextTick(() => {
					this.rebuildSelect2()
				})
			},
			deep: true,
		},

		disabled(newVal) {
			const { $, $el } = this.getJQueryElements()
			if ($ && $el) {
				$el.prop('disabled', !!newVal)
			}
		},
	},

	mounted() {
		this.$nextTick(() => {
			this.initSelect2()
		})
	},

	beforeUnmount() {
		this.destroySelect2()
	},

	methods: {
		// ---------------------------------------------------------------------
		// Helpers generales
		// ---------------------------------------------------------------------

		applyTranslate(value) {
			if (value == null) {
				return ''
			}

			// Si existe this.translate (globalProperties.translate) la usamos
			if (typeof this.translate === 'function') {
				return this.translate(value)
			}

			// Fallback directo a window.translate si está definido
			if (typeof window !== 'undefined' && typeof window.translate === 'function') {
				return window.translate(value)
			}

			return value
		},

		/**
		 * Extrae todas las cadenas de un valor que puede ser:
		 * - string simple
		 * - array de strings
		 * - objeto con claves de idioma { es: 'Chile', en: 'Chile', ... }
		 * - objetos/arrays anidados (por si acaso)
		 */
		collectLabelStrings(source) {
			const result = []

			const walk = value => {
				if (value == null) {
					return
				}
				if (typeof value === 'string') {
					if (value.trim() !== '') {
						result.push(value)
					}
					return
				}
				if (Array.isArray(value)) {
					value.forEach(v => walk(v))
					return
				}
				if (typeof value === 'object') {
					Object.values(value).forEach(v => walk(v))
					return
				}
			}

			walk(source)

			return result
		},

		getJQueryElements() {
			if (typeof window === 'undefined' || !this.$refs.select) {
				return { $, $el: null }
			}
			const w = window
			const $ = w.jQuery || w.$
			if (!$) {
				return { $, $el: null }
			}
			const $el = $(this.$refs.select)
			return { $, $el }
		},

		// ---------------------------------------------------------------------
		// Select2: init / destroy / sync
		// ---------------------------------------------------------------------

		initSelect2() {
			const { $, $el } = this.getJQueryElements()
			if (!$ || !$el || !$el.select2) {
				// jQuery o Select2 no disponibles
				return
			}

			const config = {
				placeholder: this.placeholder || '',
				allowClear: this.isNullable, // semántica: solo false deshabilita
				width: '100%',
			}

			if (!this.searchEnabled) {
				config.minimumResultsForSearch = Infinity
			}

			if (this.accentInsensitive) {
				config.matcher = this.buildMatcher()
			}

			$el.select2(config)

			// Eventos de cambio
			$el.on('change.select2', this.handleSelect2Change)

			// Interceptar selección del sentinel: emitir null pero dejar el control "vacío" (placeholder visible)
			$el.on('select2:select', this.handleSelect2Select)

			// Estado inicial (empty on init permitido siempre)
			this.syncFromModel()
		},

		destroySelect2() {
			const { $, $el } = this.getJQueryElements()
			if (!$ || !$el) {
				return
			}

			$el.off('.select2')

			if ($el.data('select2')) {
				$el.select2('destroy')
			}
		},

		rebuildSelect2() {
			this.destroySelect2()
			this.initSelect2()
		},

		syncFromModel() {
			const { $, $el } = this.getJQueryElements()
			if (!$ || !$el) {
				return
			}

			// IMPORTANTE:
			// - Queremos que null se vea como "placeholder" (selección vacía)
			// - NO queremos que la opción sentinel quede seleccionada en estado normal
			const val =
				this.internalValue === null || typeof this.internalValue === 'undefined'
					? ''
					: String(this.internalValue)

			this.isSyncingFromModel = true
			$el.val(val).trigger('change.select2')
			this.isSyncingFromModel = false
		},

		// ---------------------------------------------------------------------
		// Matcher para búsqueda insensible a mayúsculas y tildes
		// - Soporta optgroup.
		// - Usa TODAS las variantes de label (multi-idioma) + grupo.
		// ---------------------------------------------------------------------

		buildMatcher() {
			const vm = this

			const normalize = str => {
				if (!str) return ''
				return str
					.toString()
					.toLowerCase()
					.normalize('NFD')
					.replace(/[\u0300-\u036f]/g, '')
			}

			function matcher(params, data) {
				const term = normalize(params.term)

				// Sin término: mostrar todo tal cual
				if (!term) {
					return data
				}

				if (!data) {
					return null
				}

				// Caso grupo: data.children existe
				if (Array.isArray(data.children) && data.children.length > 0) {
					const matchedChildren = []

					data.children.forEach(child => {
						const match = matcher(params, child)
						if (match != null) {
							matchedChildren.push(match)
						}
					})

					if (matchedChildren.length > 0) {
						// Devolvemos una copia del grupo con solo los hijos coincidentes
						const cloned = { ...data }
						cloned.children = matchedChildren
						return cloned
					}

					// Si ningún hijo coincide, descartamos el grupo completo
					return null
				}

				// Caso opción simple (sin children)
				if (typeof data.text === 'undefined') {
					return null
				}

				// Texto base (lo que muestra Select2)
				let combinedText = normalize(data.text)

				// Buscamos la opción normalizada para añadir todas las variantes de label
				if (data.id != null) {
					const opt = vm.normalizedOptions.find(
						o => String(o.value) === String(data.id),
					)

					if (opt && opt.searchText) {
						const extra = normalize(opt.searchText)
						if (extra && extra !== combinedText) {
							combinedText += ' ' + extra
						}
					}
				}

				if (combinedText.indexOf(term) > -1) {
					return data
				}

				return null
			}

			return matcher
		},

		// ---------------------------------------------------------------------
		// Resolución de metadatos de la opción seleccionada
		// ---------------------------------------------------------------------

		resolveOptionMeta(value) {
			let label = ''
			let group = null
			let rawOption = null

			if (value === null || typeof value === 'undefined') {
				return { label, group, rawOption }
			}

			// Sentinel: es "acción limpiar", no un valor real
			if (String(value) === String(this.nullSentinel)) {
				return {
					label: this.nullableNullOptionText || '',
					group: null,
					rawOption: null,
				}
			}

			const src = this.options || []
			const valueStr = String(value)

			// Partimos siempre de normalizedOptions para label/group traducidos
			const norm = this.normalizedOptions.find(
				o => String(o.value) === valueStr,
			)

			if (norm) {
				label = norm.label
				group = norm.group ?? null
			}

			// Caso A: array tal como viene de BD → buscamos fila original
			if (Array.isArray(src)) {
				const found = src.find(item => {
					if (!item || typeof item !== 'object') return false
					const v = item[this.valueField]
					return String(v) === valueStr
				})
				if (found) {
					rawOption = found
				}
			}
			// Caso B: mapa value => label (solo si NO hay groupField)
			else if (!this.groupField && src && typeof src === 'object') {
				let originalLabel

				if (Object.prototype.hasOwnProperty.call(src, valueStr)) {
					originalLabel = src[valueStr]
				} else if (Object.prototype.hasOwnProperty.call(src, value)) {
					originalLabel = src[value]
				}

				if (typeof originalLabel !== 'undefined') {
					if (!label) {
						label = this.applyTranslate(originalLabel)
					}
					rawOption = {
						value,
						label: originalLabel,
					}
				}
			}

			return { label, group, rawOption }
		},

		// ---------------------------------------------------------------------
		// Interceptar selección (para opción sentinel)
		// ---------------------------------------------------------------------

		handleSelect2Select(e) {
			if (this.isSyncingFromModel) {
				return
			}

			const selectedId = e?.params?.data?.id

			// Solo aplica si tenemos opción "limpiar" visible (nullable string)
			if (!this.nullableNullOptionText) {
				return
			}

			if (String(selectedId) !== String(this.nullSentinel)) {
				return
			}

			const previousValue = this.lastEmittedValue

			// Si NO es nullable y ya teníamos un valor real, no permitimos volver a null
			if (!this.isNullable && previousValue !== null) {
				this.$nextTick(() => {
					this.syncFromModel()
				})
				return
			}

			// Emitimos null, pero dejamos el control visualmente vacío para que se vea el placeholder.
			this.isHandlingSentinelClear = true

			const { label, group, rawOption } = this.resolveOptionMeta(this.nullSentinel)

			this.internalValue = null
			this.lastEmittedValue = null

			this.$emit('update:modelValue', null)
			this.$emit('select2-change', {
				value: null,
				label,
				group,
				rawOption,
				previousValue,
				isClear: true,
				id: this.id || null,
				name: this.name || null,
			})

			const { $, $el } = this.getJQueryElements()
			if ($ && $el) {
				// Forzamos vacío (placeholder)
				this.isSyncingFromModel = true
				$el.val('').trigger('change.select2')
				this.isSyncingFromModel = false

				// Cerrar dropdown (opcional, ayuda UX)
				try {
					$el.select2('close')
				} catch (err) {
					// noop
				}
			}

			// Desactivar el guard para el próximo tick (por si change se dispara de forma asíncrona)
			this.$nextTick(() => {
				this.isHandlingSentinelClear = false
			})
		},

		// ---------------------------------------------------------------------
		// Manejo de cambios (UI → Vue)
		// ---------------------------------------------------------------------

		handleSelect2Change(e) {
			if (this.isSyncingFromModel) {
				return
			}

			// Si acabamos de procesar el sentinel y luego disparamos un change programático,
			// no queremos volver a emitir nada aquí.
			if (this.isHandlingSentinelClear) {
				return
			}

			const { $, $el } = this.getJQueryElements()
			if (!$ || !$el) {
				return
			}

			const raw = $el.val()
			const previousValue = this.lastEmittedValue

			let value = null

			if (raw !== '' && raw !== null && typeof raw !== 'undefined') {
				// Intentamos parsear número, pero si no es numérico dejamos string
				const num = Number(raw)
				value = Number.isNaN(num) ? raw : num
			} else {
				value = null
			}

			// Si NO es nullable y ya teníamos un valor real, no permitimos volver a null
			if (value === null && !this.isNullable && previousValue !== null) {
				this.internalValue = previousValue
				this.$nextTick(() => {
					this.syncFromModel()
				})
				return
			}

			const isClear = value === null

			const { label, group, rawOption } = this.resolveOptionMeta(raw)

			this.internalValue = value
			this.lastEmittedValue = value

			this.$emit('update:modelValue', value)
			this.$emit('select2-change', {
				value,
				label,
				group,
				rawOption,
				previousValue,
				isClear,
				id: this.id || null,
				name: this.name || null,
			})
		},
	},
}
</script>
