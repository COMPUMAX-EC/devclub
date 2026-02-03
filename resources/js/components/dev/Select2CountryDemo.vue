<!-- resources/js/components/dev/Select2CountryDemo.vue -->
<template>
	<div class="row g-5">
		<div class="col-md-4">
			<h3 class="mb-3">Select2 simple</h3>

			<select2
				v-model="countryIdSimple"
				:options="initialCountries"
				value-field="id"
				label-field="name"
				placeholder="Seleccione un país"
				:search-enabled="true"
				:accent-insensitive="true"
				:nullable="false"
				:allow-empty-on-init="true"
				@select2-change="onSimpleSelect2Change"
			/>

			<div class="mt-2 small text-muted">
				ID seleccionado (simple):
				<strong>{{ countryIdSimple === null ? 'null' : countryIdSimple }}</strong>
			</div>

			<pre
				v-if="lastSimpleEvent !== null"
				class="mt-2 small bg-light p-2 rounded"
			>{{ JSON.stringify(lastSimpleEvent, null, 2) }}</pre>
		</div>

		<div class="col-md-4">
			<h3 class="mb-3">Select2 agrupado</h3>

			<select2
				v-model="countryIdGrouped"
				:options="initialCountries"
				value-field="id"
				label-field="name"
				group-field="continent_code"
				placeholder="Seleccione un país (agrupado)"
				:search-enabled="true"
				:accent-insensitive="true"
				:nullable="true"
				:allow-empty-on-init="true"
				@select2-change="onGroupedSelect2Change"
			/>

			<div class="mt-2 small text-muted">
				ID seleccionado (agrupado):
				<strong>{{ countryIdGrouped === null ? 'null' : countryIdGrouped }}</strong>
			</div>

			<pre
				v-if="lastGroupedEvent !== null"
				class="mt-2 small bg-light p-2 rounded"
			>{{ JSON.stringify(lastGroupedEvent, null, 2) }}</pre>
		</div>

		<div class="col-md-4">
			<h3 class="mb-3">Select2 [value ⇒ label]</h3>

			<select2
				v-model="countryIso3Selected"
				:options="countriesIso3Map"
				placeholder="Seleccione un país (ISO3)"
				:search-enabled="true"
				:accent-insensitive="true"
				:nullable="true"
				:allow-empty-on-init="true"
				@select2-change="onIso3Select2Change"
			/>

			<div class="mt-2 small text-muted">
				Código ISO3 seleccionado:
				<strong>{{ countryIso3Selected === null ? 'null' : countryIso3Selected }}</strong>
			</div>

			<pre
				v-if="lastIso3Event !== null"
				class="mt-2 small bg-light p-2 rounded"
			>{{ JSON.stringify(lastIso3Event, null, 2) }}</pre>
		</div>
	</div>
</template>

<script>
import Select2 from '@/components/ui/Select2.vue'

export default {
	name: 'Select2CountryDemo',

	components: {
		Select2,
	},

	props: {
		/**
		 * Lista tal como viene de BD, SIN tocar:
		 * [
		 *   { id, name, iso2, continent_code, ... },
		 *   ...
		 * ]
		 */
		initialCountries: {
			type: Array,
			default: () => [],
		},

		/**
		 * Mapa simple value => label
		 * {
		 *   "CHL": "Chile",
		 *   "ARG": "Argentina",
		 *   ...
		 * }
		 *
		 * Se envía desde el controlador.
		 */
		countriesIso3Map: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			countryIdSimple: null,
			countryIdGrouped: null,
			countryIso3Selected: null,

			lastSimpleEvent: null,
			lastGroupedEvent: null,
			lastIso3Event: null,
		}
	},

	watch: {
		initialCountries: {
			deep: true,
			handler(newVal) {
				console.log(
					'[Select2CountryDemo] watch initialCountries → esArray =',
					Array.isArray(newVal),
					'length =',
					Array.isArray(newVal) ? newVal.length : 'N/A',
				)
				if (Array.isArray(newVal)) {
					console.log('[Select2CountryDemo] watch initialCountries ejemplo[0] =', newVal[0])
				}
			},
		},

		countriesIso3Map: {
			deep: true,
			handler(newVal) {
				const isObj =
					newVal !== null && typeof newVal === 'object' && !Array.isArray(newVal)
				console.log(
					'[Select2CountryDemo] watch countriesIso3Map → esObjetoPlano =',
					isObj,
					'tipo =',
					typeof newVal,
				)
				if (isObj) {
					const keys = Object.keys(newVal)
					console.log(
						'[Select2CountryDemo] watch countriesIso3Map keys.length =',
						keys.length,
						'keys (primeros 10) =',
						keys.slice(0, 10),
					)
					if (keys.length > 0) {
						const k0 = keys[0]
						console.log(
							'[Select2CountryDemo] watch countriesIso3Map ejemplo →',
							k0,
							'=>',
							newVal[k0],
						)
					}
				}
			},
		},
	},

	mounted() {
		console.log('[Select2CountryDemo] mounted')
		console.log(
			'[Select2CountryDemo] mounted initialCountries → esArray =',
			Array.isArray(this.initialCountries),
			'length =',
			Array.isArray(this.initialCountries) ? this.initialCountries.length : 'N/A',
		)
		if (Array.isArray(this.initialCountries) && this.initialCountries.length > 0) {
			console.log(
				'[Select2CountryDemo] mounted initialCountries[0] =',
				this.initialCountries[0],
			)
		}

		const isObj =
			this.countriesIso3Map !== null &&
			typeof this.countriesIso3Map === 'object' &&
			!Array.isArray(this.countriesIso3Map)

		console.log(
			'[Select2CountryDemo] mounted countriesIso3Map → esObjetoPlano =',
			isObj,
			'tipo =',
			typeof this.countriesIso3Map,
		)

		if (isObj) {
			const keys = Object.keys(this.countriesIso3Map)
			console.log(
				'[Select2CountryDemo] mounted countriesIso3Map keys.length =',
				keys.length,
				'keys (primeros 10) =',
				keys.slice(0, 10),
			)
			if (keys.length > 0) {
				const k0 = keys[0]
				console.log(
					'[Select2CountryDemo] mounted countriesIso3Map ejemplo →',
					k0,
					'=>',
					this.countriesIso3Map[k0],
				)
			}
		}
	},

	methods: {
		onSimpleSelect2Change(value) {
			this.lastSimpleEvent = value
			console.log('[Select2CountryDemo] simple select2-change value =', value)
		},

		onGroupedSelect2Change(value) {
			this.lastGroupedEvent = value
			console.log('[Select2CountryDemo] grouped select2-change value =', value)
		},

		onIso3Select2Change(value) {
			this.lastIso3Event = value
			console.log('[Select2CountryDemo] iso3 select2-change value =', value)
		},
	},
}
</script>
