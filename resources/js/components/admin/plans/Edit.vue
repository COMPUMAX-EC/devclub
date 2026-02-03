<!-- /resources/js/components/admin/plans/Edit.vue -->
<template>
	<div>
		<!-- Header -->
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h1 class="mb-0">
				Versión: {{ planVersion.name }}
				<span
					class="badge"
					:class="planVersion.status === 'active' ? 'bg-success' : 'bg-warning'"
				>
					{{ planVersion.status === 'active' ? 'Activa' : 'Inactiva' }}
				</span>
			</h1>
			<a
				:href="route('admin.products.plans.pdf', { product: this.product.id, planVersion: planVersion.id })"
				target="_blank"
				class="btn btn-sm btn-danger"
			>
				Previsualizar PDF
			</a>
		</div>

		<!-- TARJETA: Parámetros de la versión -->
		<div class="card mb-6">
			<div class="card-body">
				<div class="row g-3">
					<div class="col-md-12 fv-row mb-3">
						<label class="form-label">Nombre de la versión</label>
						<div class="input-group">
							<input
								type="text"
								class="form-control"
								placeholder="Nombre de la versión"
								v-model="planVersion.name"
								@input="onVersionNameInput"
								id="nombre-version"
							>
							<!-- Botón ACTIVAR -->
							<button
								v-if="planVersion.status !== 'active'"
								type="button"
								class="btn btn-sm btn-success"
								:disabled="!planVersion.can_be_activated || isTogglingStatus"
								@click="activateVersion"
							>
								<span v-if="!isTogglingStatus">Activar versión</span>
								<span
									v-else
									class="spinner-border spinner-border-sm"
									role="status"
									aria-hidden="true"
								></span>
							</button>

							<!-- Botón DESACTIVAR -->
							<button
								v-else
								type="button"
								class="btn btn-sm btn-warning"
								:disabled="isTogglingStatus"
								@click="deactivateVersion"
							>
								<span v-if="!isTogglingStatus">Desactivar versión</span>
								<span
									v-else
									class="spinner-border spinner-border-sm"
									role="status"
									aria-hidden="true"
								></span>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Edades -->
		<div class="d-flex justify-content-between align-items-center mb-2">
			<h3 class="h3 mb-0">Edades</h3>
		</div>

		<div class="card mb-6">
			<div class="card-body">
				<div class="row g-3">
					<!-- 1. Edad máxima de entrada -->
					<div class="col-md-3 fv-row mb-3">
						<label class="form-label">Edad máxima de entrada</label>
						<div class="input-group">
							<input
								type="text"
								class="form-control"
								v-model="versionIntegerInputs.max_entry_age"
								@input="onVersionIntegerFieldInput('max_entry_age')"
								inputmode="numeric"
							>
							<label class="input-group-text">Años</label>
						</div>
					</div>

					<!-- 2. Edad máxima para renovación -->
					<div class="col-md-3 fv-row mb-3">
						<label class="form-label">Edad máxima para renovación</label>
						<div class="input-group">
							<input
								type="text"
								class="form-control"
								v-model="versionIntegerInputs.max_renewal_age"
								@input="onVersionIntegerFieldInput('max_renewal_age')"
								inputmode="numeric"
							>
							<label class="input-group-text">Años</label>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Carencias -->
		<div class="d-flex justify-content-between align-items-center mb-2">
			<h3 class="h3 mb-0">Carencias</h3>
		</div>
		<div class="card mb-6">
			<div class="card-body">
				<div class="row g-3">
					<!-- 3. Carencia por suicidio (días) -->
					<div class="col-md-3 fv-row mb-3">
						<label class="form-label">Carencia por suicidio</label>
						<div class="input-group">
							<input
								type="text"
								class="form-control"
								v-model="versionIntegerInputs.wtime_suicide"
								@input="onVersionIntegerFieldInput('wtime_suicide')"
								inputmode="numeric"
							>
							<label class="input-group-text">Días</label>
						</div>
					</div>

					<!-- 4. Carencia por condiciones preexistentes (días) -->
					<div class="col-md-3 fv-row mb-3">
						<label class="form-label">Carencia condiciones preexistentes</label>
						<div class="input-group">
							<input
								type="text"
								class="form-control"
								v-model="versionIntegerInputs.wtime_preexisting_conditions"
								@input="onVersionIntegerFieldInput('wtime_preexisting_conditions')"
								inputmode="numeric"
							>
							<label class="input-group-text">Días</label>
						</div>
					</div>

					<!-- 5. Carencia por accidente (días) -->
					<div class="col-md-3 fv-row mb-3">
						<label class="form-label">Carencia por accidente</label>
						<div class="input-group">
							<input
								type="text"
								class="form-control"
								v-model="versionIntegerInputs.wtime_accident"
								@input="onVersionIntegerFieldInput('wtime_accident')"
								inputmode="numeric"
							>
							<label class="input-group-text">Días</label>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Tarifa base + países -->
		<div class="d-flex justify-content-between align-items-center mb-2">
			<h3 class="h3 mb-0">Tarifas por residencia habitual</h3>
		</div>
		<div class="card mb-6">
			<div class="card-body">
				<!-- Precio base -->
				<div class="mb-5">
					<label class="form-label">Precio default</label>
					<div class="input-group input-group-sm">
						<span class="input-group-text">USD</span>
						<input
							type="text"
							class="form-control"
							v-model="versionDecimalInputs.price_1"
							@input="onVersionDecimalFieldInput('price_1')"
							inputmode="decimal"
							autocomplete="off"
						>
						<button
							type="button"
							class="btn btn-sm btn-light-primary"
							:disabled="isLoadingPlanCountries"
							@click="openCountriesModal"
						>
							Gestionar países
						</button>
					</div>
				</div>

				<!-- Países de la versión -->
				<div class="mb-3">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<div class="form-label">Tarifa por país</div>
						<div class="d-flex align-items-center">
							<div class="text-muted fs-7 me-3">
								{{ planCountries.length }} países asignados
							</div>
						</div>
					</div>

					<div
						v-if="isLoadingPlanCountries"
						class="text-center text-muted py-4"
					>
						Cargando países del plan…
					</div>

					<div
						v-else-if="!planCountries.length"
						class="text-muted fs-7"
					>
						No hay países asignados a esta versión. Usa el botón
						<strong>“Gestionar países”</strong> para añadirlos por país o por zona.
					</div>

					<div
						v-else
					>
						<div class="row">
							<div class="col-xxl-4 col-xl-6 col-lg-6 col-md-12 mb-3" v-for="country in planCountries" :key="country.id">
								<div :class="{'custom_price':countryPriceInputs[country.id]!=''}"  class="input-group input-group-sm">
									<span class="input-group-text country_name" style="min-width: 7rem; max-width: 50%; overflow: hidden;">{{ translate(country.name) }}</span>
									<input
										type="text"
										class="form-control form-control-sm text-end price_value"
										
										v-model="countryPriceInputs[country.id]"
										inputmode="decimal"
										:disabled="isLoadingPlanCountries"
										:placeholder="versionDecimalInputs.price_1"
										@input="onCountryPriceInput(country)"
									>
									<button
										type="button"
										class="btn btn-sm btn-light"
										:disabled="isLoadingPlanCountries"
										@click="clearCountryPrice(country)"
										title="Quitar precio específico (usar precio base)"
									>
										<i class="bi bi-x"></i>
									</button>
									<button
										type="button"
										class="btn btn-sm btn-light-danger"
										:disabled="isLoadingPlanCountries"
										@click="confirmDetachCountry(country)"
										title="Quitar país de la versión"
									>
										<i class="bi bi-trash"></i>
									</button>

								</div>
							</div>
						</div>
					</div>

					<div class="form-text mt-3">
						Si no se define un precio para un país concreto, se aplicará el
						<strong>precio base</strong>.
					</div>
				</div>
			</div>
		</div>

		<!-- Recargos por rango de edad -->
		<div class="d-flex justify-content-between align-items-center mb-2">
			<h3 class="h3 mb-0">Recargos por rango de edad</h3>
			
			<button
				type="button"
				class="btn btn-sm btn-light-primary"
				@click="addAgeSurchargeRow"
			>
				+ Añadir rango
			</button>
		</div>
		<div class="card mb-6">
			<div class="card-body">
				<div v-if="isLoadingAgeSurcharges" class="text-muted">
					Cargando recargos…
				</div>
				<div v-else>
					<div
						v-if="!ageSurcharges.length"
						class="text-muted fs-7 mb-3"
					>
						No hay recargos definidos. Usa el botón «Añadir rango» para crear el primero.
					</div>

					<div
						v-if="ageSurcharges.length"
					>
						<table class="table table-row-dashed table-hover table-condensed align-middle mb-0 float-error">
							<thead>
								<tr>
									<th style="width: 120px;">Desde</th>
									<th style="width: 120px;">Hasta</th>
									<th style="width: 180px;">Recargo %</th>
									<th style="width: 80px;" class="text-end">Acción</th>
								</tr>
							</thead>
							<tbody>
								<tr
									v-for="row in ageSurcharges"
									:key="row._autosaveKey"
								>
									<td>
										<div class="mb-1" style="position: relative;">
											<input
												type="text"
												class="form-control form-control-sm"
												v-model="row._ageFromInput"
												inputmode="numeric"
												:class="{ 'is-invalid': hasAgeFromError(row) }"
												@input="onAgeFromInput(row)"
											>
											<!-- Mensajes solo para orden / solapamiento, NO para requerido vacío -->
											<div
												class="invalid-feedback"
												v-if="row._errors && (row._errors.fromOrder || row._errors.overlap)"
											>
												<template v-if="row._errors.fromOrder">
													Error edad DESDE mayor a edad HASTA
												</template>
												<template v-else-if="row._errors.overlap">
													Este rango se solapa con otro.
												</template>
											</div>
										</div>
									</td>
									<td>
										<div class="mb-1" style="position: relative;">
											<input
												type="text"
												class="form-control form-control-sm"
												v-model="row._ageToInput"
												inputmode="numeric"
												:class="{
													'is-invalid':
														hasAgeToError(row) ||
														(row._errors && row._errors.overlap)
												}"
												@input="onAgeToInput(row)"
											>
											<!-- Solo texto para error de orden, NO para requerido vacío -->
<!--											<div
												class="invalid-feedback"
												v-if="row._errors && row._errors.toOrder"
											>
												La edad hasta debe ser mayor o igual que la edad desde.
											</div>-->
										</div>
									</td>
									<td>
										<div class="mb-1" style="max-width: 12rem;">
											<div class="input-group input-group-sm">
												<input
													type="text"
													class="form-control form-control-sm text-end"
													v-model="row._surchargeInput"
													inputmode="decimal"
													:class="{ 'is-invalid': hasSurchargeError(row) }"
													@input="onAgeSurchargeInput(row)"
												>
												<span class="input-group-text">%</span>
											</div>
											<!-- Para recargo, cuando está vacío solo borde rojo, sin texto -->
										</div>
									</td>
									<td class="text-end">
										<button
											type="button"
											class="btn btn-sm btn-light-danger"
											@click="removeAgeSurcharge(row)"
											title="Eliminar rango"
										>
											<i class="bi bi-trash"></i>
										</button>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		
		
		<!-- Recargos por rango de edad -->

		<div class="d-flex justify-content-between align-items-center mb-2">
			<div>
				<h3 class="h3 mb-0">Países permitidos para repatriación</h3>
			</div>
			<div class="d-flex align-items-center">
				<div class="text-muted fs-7 me-3">
					{{ repatriationCountries.length }} países
				</div>
				<button
					type="button"
					class="btn btn-sm btn-light-primary"
					@click="openRepatriationCountriesModal"
					:disabled="isLoadingRepatriationCountries"
				>
					Gestionar países de repatriación
				</button>
			</div>
		</div>

		<!-- Países permitidos para repatriación -->
		<div class="card mb-6">
			<div class="card-body">

				<div
					v-if="isLoadingRepatriationCountries"
					class="text-center text-muted py-4"
				>
					Cargando países permitidos para repatriación…
				</div>

				<div
					v-else-if="!repatriationCountries.length"
					class="text-muted fs-7"
				>
					No hay países definidos como permitidos para repatriación.
					Usa el botón
					<strong>“Gestionar países de repatriación”</strong>
					para añadirlos por país o por zona.
				</div>

				<div v-else class="row">
					
					<div v-for="country in repatriationCountries" :key="country.id" class="col-xxl-3 col-xl-4 col-lg-6 col-md-6 col-sm-12 mb-3">
						<div class="input-group input-group-sm">
							<span style="overflow: hidden; text-wrap: nowrap;" class="form-control form-control-sm country_name" >{{ translate(country.name) }}</span>
							<button
								type="button"
								class="btn btn-sm btn-light-danger"
								:disabled="isLoadingRepatriationCountries"
								@click="confirmDetachRepatriationCountry(country)"
								title="Quitar país de repatriación"
							>
								<i class="bi bi-trash"></i>
							</button>
						</div>
					</div>

				</div>
			</div>
		</div>

		<!-- Términos y condiciones (HTML ES / EN) -->
		<div class="d-flex justify-content-between	align-items-center mb-2">
			<h3 class="h3 mb-0">Términos y condiciones</h3>
		</div>
		<div class="card mb-6">
			<div class="card-body">
				<p class="text-muted fs-7 mb-3">
					Los términos y condiciones se almacenan en formato HTML por idioma.
					Puedes editar cada versión en un editor enriquecido.
				</p>

				<div class="d-flex flex-wrap gap-2">
					<button
						type="button"
						class="btn btn-sm btn-light-primary"
						@click="openTermsHtmlModal('es')"
					>
						Versión Español
						<span
							v-if="termsHtmlSizes.es"
							class="ms-2 text-muted small"
						>
							({{ termsHtmlSizes.es.label }})
						</span>
					</button>

					<button
						type="button"
						class="btn btn-sm btn-light-primary"
						@click="openTermsHtmlModal('en')"
					>
						Versión Inglés
						<span
							v-if="termsHtmlSizes.en"
							class="ms-2 text-muted small"
						>
							({{ termsHtmlSizes.en.label }})
						</span>
					</button>
				</div>
			</div>
		</div>

		<!-- Coberturas -->
		<div class="d-flex justify-content-between align-items-center mb-2">
			<h3 class="h3 mb-0">Coberturas</h3>
			<button class="btn btn-sm btn-primary" @click="openCoverageSelectorModal">
				+ Añadir coberturas
			</button>
		</div>

		<div v-if="coverageCategories.length === 0" class="card mb-6">
			<div class="card-body">
				Esta versión de plan aún no tiene coberturas asociadas.
			</div>
		</div>

		<div
			v-for="(category, catIndex) in coverageCategories"
			:key="category.id"
			class="card mb-6"
		>
			<div class="card-header d-flex justify-content-between align-items-center py-2">
				<div>
					<div class="fw-semibold h3">
						{{ translate(category.name) }}
					</div>
					<div
						v-if="category.description && translate(category.description)"
						class="small text-muted"
					>
						{{ translate(category.description) }}
					</div>
				</div>
			</div>

			<table class="table table-row-dashed table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th style="width: 2rem;"></th>
						<th>Cobertura</th>
						<th style="max-width:200px;">Alcance</th>
						<th>Notas</th>
						<th style="width: 4rem;"></th>
					</tr>
				</thead>
				<transition-group tag="tbody" name="row">
					<tr
						v-for="(cov, index) in category.coverages"
						:key="cov.id"
						@dragover.prevent
						@dragenter.prevent="onCoverageDragEnter(category.id, cov.id)"
						:class="{
							'table-active': isDragging &&
								dragCategoryId === category.id &&
								dragOverCoverageId === cov.id
						}"
					>
						<td>
							<span
								class="drag-handle"
								draggable="true"
								@dragstart="onCoverageDragStart(category.id, cov.id, $event)"
								@dragend="onCoverageDragEnd"
								title="Arrastrar para reordenar"
							>
								<i class="fa-solid fa-up-down"></i>
							</span>
						</td>
						<td>
							<div class="main">{{ translate(cov.coverage_name) }}</div>
							<div
								class="small text-muted"
								v-if="translate(cov.coverage_description) !== ''"
							>
								{{ translate(cov.coverage_description) }}
							</div>
						</td>

						<!-- Alcance -->
						<td>
							<template v-if="cov.unit_measure_type === 'integer'">
								<div class="input-group">
									<input
										type="text"
										class="form-control"
										v-model="cov._valueIntInput"
										@input="onIntegerInput(cov)"
									>
									<span class="input-group-text">{{ translate(cov.unit_name) }}</span>
								</div>
							</template>

							<template v-else-if="cov.unit_measure_type === 'decimal'">
								<div class="input-group">
									<input
										type="text"
										class="form-control"
										v-model="cov._valueDecimalInput"
										@input="onDecimalInput(cov)"
									>
									<span class="input-group-text">{{ translate(cov.unit_name) }}</span>
								</div>
							</template>

							<template v-else-if="cov.unit_measure_type === 'text'">
								<div class="mb-1">
									<input
										type="text"
										class="form-control mb-1"
										v-model="cov.value_text.es"
										placeholder="Texto (ES)"
										@input="onTranslatableChanged(cov, 'value_text')"
									>
									<input
										type="text"
										class="form-control"
										v-model="cov.value_text.en"
										placeholder="Texto (EN)"
										@input="onTranslatableChanged(cov, 'value_text')"
									>
								</div>
							</template>

							<template v-else>
								{{ translate(cov.unit_name) }}
							</template>
						</td>

						<td style="max-width:200px;">
							<input
								type="text"
								class="form-control mb-1"
								v-model="cov.notes.es"
								placeholder="Notas (ES)"
								@input="onTranslatableChanged(cov, 'notes')"
							>
							<input
								type="text"
								class="form-control"
								v-model="cov.notes.en"
								placeholder="Notas (EN)"
								@input="onTranslatableChanged(cov, 'notes')"
							>
						</td>

						<td class="text-end">
							<button
								type="button"
								class="btn btn-sm btn-danger"
								@click="removeCoverageFromVersion(cov)"
							>
								<i class="bi bi-trash"></i>
							</button>
						</td>
					</tr>

					<tr v-if="category.coverages.length === 0">
						<td colspan="5" class="text-muted small">
							No hay coberturas en esta categoría.
						</td>
					</tr>
				</transition-group>
			</table>
		</div>

		<!-- Modal selector de coberturas -->
		<div class="modal fade" tabindex="-1" ref="coverageSelectorModal">
			<div class="modal-dialog modal-xl">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Añadir / quitar coberturas</h5>
						<button
							type="button"
							class="btn-close"
							@click="closeCoverageSelectorModal"
						></button>
					</div>
					<table class="table table-row-dashed table-striped">
						<thead>
							<tr>
								<th>Categoría</th>
								<th>Cobertura</th>
								<th>Unidad</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<tr
								v-for="(row, index) in availableCoverages"
								:key="row.id"
								:class="{
									'border_color_darker':
										index === 0 ||
										row.category_name !== availableCoverages[index - 1].category_name
								}"
							>
								<td>{{ translate(row.category_name) }}</td>
								<td>
									<div class="main">{{ translate(row.coverage_name) }}</div>
									<div
										v-if="row.coverage_description && translate(row.coverage_description)"
										class="small text-muted"
									>
										{{ translate(row.coverage_description) }}
									</div>
								</td>
								<td>{{ translate(row.unit_name) }}</td>
								<td class="text-end">
									<button
										v-if="!row.attached"
										type="button"
										class="btn btn-sm btn-light-primary"
										@click="attachCoverage(row)"
									>
										+ Añadir
									</button>
									<button
										v-else
										type="button"
										class="btn btn-sm btn-light-danger"
										@click="detachCoverage(row)"
									>
										Quitar
									</button>
								</td>
							</tr>

							<tr v-if="availableCoverages.length === 0">
								<td colspan="4" class="text-muted small">No hay coberturas disponibles.</td>
							</tr>
						</tbody>
					</table>
					<div class="modal-footer">
						<button
							type="button"
							class="btn btn-sm btn-secondary"
							@click="closeCoverageSelectorModal"
						>
							Cerrar
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal países del plan -->
		<admin-plans-countries-modal
			ref="countriesModal"
			:product-id="product.id"
			:plan-version-id="planVersion.id"
			@create="onCountriesCreated"
			@detach="onCountriesDetached"
		/>

		<!-- Modal países permitidos para repatriación -->
		<admin-plans-repatriation-countries-modal
			ref="repatriationCountriesModal"
			:product-id="product.id"
			:plan-version-id="planVersion.id"
			@create="onRepatriationCountriesCreated"
			@detach="onRepatriationCountriesDetached"
		/>

		<!-- Modal términos HTML (ES / EN) -->
		<admin-plans-terms-html-modal
			ref="termsHtmlModal"
			:product-id="product.id"
			:plan-version-id="planVersion.id"
			@updated="onTermsHtmlUpdated"
		/>

		<!-- Modal edición de producto -->
		<admin-products-edit-modal
			ref="productModal"
			:product-types="productTypes"
			@updated="onProductUpdated"
		/>
	</div>
</template>

<script>
import axios from 'axios'
import * as format from '@/utils/format'

export default {
	name: 'AdminPlansVersionEdit',

	props: {
		initialProduct: { type: Object, required: true },
		initialPlanVersion: { type: Object, required: true },
		initialCoverageCategories: { type: Array, default: () => [] },
		productTypes: { type: Array, default: () => [] },
	},

	data() {
		return {
			product: JSON.parse(JSON.stringify(this.initialProduct || {})),
			planVersion: JSON.parse(JSON.stringify(this.initialPlanVersion || {})),
			coverageCategories: JSON.parse(JSON.stringify(this.initialCoverageCategories || [])),

			autosaveTimers: {},
			autosaveDelay: window.__RUNTIME_CONFIG__.autosaveDelayMs,

			versionNameTimer: null,

			isDragging: false,
			dragCategoryId: null,
			dragCoverageId: null,
			dragOverCoverageId: null,

			availableCoverages: [],
			coverageSelectorModalInstance: null,

			versionIntegerInputs: {
				max_entry_age: '',
				max_renewal_age: '',
				wtime_suicide: '',
				wtime_preexisting_conditions: '',
				wtime_accident: '',
			},
			versionDecimalInputs: {
				price_1: '',
			},

			// Países y tarifas
			planCountries: [],
			isLoadingPlanCountries: false,
			countryPriceInputs: {},
			countryPriceLastSavedDisplay: {},
			countryPriceLastSavedValue: {},

			// Países permitidos para repatriación
			repatriationCountries: [],
			isLoadingRepatriationCountries: false,

			// Recargos por rango de edad
			ageSurcharges: [],
			isLoadingAgeSurcharges: false,
			ageSurchargesValidationTimer: null,

			// Tamaños de HTML de términos por idioma
			termsHtmlSizes: {
				es: null,
				en: null,
			},

			isTogglingStatus: false,
		}
	},

	async mounted() {
		this.initFormattedNumericFields()
		this.initVersionNumericFields()

		await this.loadPlanCountries()
		await this.loadRepatriationCountries()
		await this.loadAgeSurcharges()
		await this.loadTermsHtmlSizes()

		if (typeof window !== 'undefined' && window.bootstrap && this.$refs.coverageSelectorModal) {
			const { Modal } = window.bootstrap
			this.coverageSelectorModalInstance = Modal.getOrCreateInstance(
				this.$refs.coverageSelectorModal,
				{ backdrop: 'static' },
			)
		}
	},

	computed: {
		versionsIndexUrl() {
			return this.route('admin.products.plans.index', { product: this.product.id })
		},
	},

	methods: {
		/* =============================================================
		 *                    Helpers toast
		 * ============================================================= */
		notifyFromResponse(data, fallbackMessage = null, fallbackType = 'success') {
			const toast = data?.toast
			const msg = toast?.message || data?.message || null
			const type = toast?.type || fallbackType

			if (!msg) {
				if (fallbackMessage) {
					flash(fallbackMessage, fallbackType)
				}
				return
			}

			flash(msg, type)
		},

		notifyFromError(e, fallbackMessage) {

			const toast = e?.response?.data?.toast
			if (toast?.message) {
				flash(toast.message, toast.type || 'danger')
				return
			}

			const msg = e?.response?.data?.message
			if (msg) {
				flash(msg, 'danger')
				return
			}

			const errors = e?.response?.data?.errors
			if (errors && typeof errors === 'object') {
				const firstKey = Object.keys(errors)[0]
				const firstVal = firstKey ? errors[firstKey] : null
				if (Array.isArray(firstVal) && firstVal[0]) {
					flash(firstVal[0], 'danger')
					return
				}
			}

			if (fallbackMessage) {
				flash(fallbackMessage, 'danger')
				return
			}

			flash(e?.message || 'Error inesperado.', 'danger')
		},

		/* =============================================================
		 *                    Países del plan / tarifas
		 * ============================================================= */

		async loadPlanCountries() {
			this.isLoadingPlanCountries = true
			try {
				const url = this.route('admin.products.plans.countries.index', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})

				const { data } = await axios.get(url)

				// Soportar respuesta plana y anidada en data.data.plan_countries
				const raw = data || {}
				const payload = raw && raw.data ? raw.data : raw

				let list = []
				if (Array.isArray(payload.plan_countries)) {
					list = payload.plan_countries
				} else if (Array.isArray(payload.countries)) {
					list = payload.countries
				} else if (Array.isArray(raw.plan_countries)) {
					list = raw.plan_countries
				} else if (Array.isArray(raw.countries)) {
					list = raw.countries
				}

				this.planCountries = list.map(item => {
					const copy = { ...item }
					const fromPivot = copy.pivot && typeof copy.pivot.price !== 'undefined'
					const rawPrice = fromPivot ? copy.pivot.price : copy.price

					let numeric = null
					if (rawPrice != null) {
						const n = Number(rawPrice)
						if (!Number.isNaN(n)) numeric = n
					}

					copy.price = numeric
					return copy
				})

				this.initializeCountriesPriceState(this.planCountries)
				this.sortPlanCountries()
			} catch (e) {

				this.planCountries = []
				this.countryPriceInputs = {}
				this.countryPriceLastSavedDisplay = {}
				this.countryPriceLastSavedValue = {}
				this.notifyFromError(e, 'No se pudo cargar los países del plan.')
			} finally {
				this.isLoadingPlanCountries = false
			}
		},

		initializeCountriesPriceState(countries) {
			if (!Array.isArray(countries)) return

			countries.forEach(country => {
				if (!country || typeof country.id === 'undefined') return

				const id = country.id
				let numeric = null

				if (country.price != null) {
					const n = Number(country.price)
					if (!Number.isNaN(n)) {
						numeric = n
					}
				}

				const display = numeric != null ? format.formatDecimal(numeric) : ''

				country.price = numeric
				this.countryPriceLastSavedValue[id] = numeric
				this.countryPriceLastSavedDisplay[id] = display
				this.countryPriceInputs[id] = display
			})
		},

		sortPlanCountries() {
			const t = this.translate || (v => v)
			this.planCountries.sort((a, b) => {
				const an = (t(a.name) || '').toString()
				const bn = (t(b.name) || '').toString()

				if (an < bn) return -1
				if (an > bn) return 1
				return 0
			})
		},

		openCountriesModal() {
			if (this.$refs.countriesModal && typeof this.$refs.countriesModal.open === 'function') {
				this.$refs.countriesModal.open()
			}
		},

		onCountriesCreated(countries) {
			this.applyCreatedCountries(countries)
		},

		onCountriesDetached(countries) {
			this.applyDetachedCountries(countries)
		},

		applyCreatedCountries(countries) {
			if (!Array.isArray(countries) || countries.length === 0) return

			const byId = new Map(this.planCountries.map(c => [c.id, c]))

			countries.forEach(country => {
				if (!country || typeof country.id === 'undefined') return

				const id = country.id
				const existing = byId.get(id)

				const fromPivot = country.pivot && typeof country.pivot.price !== 'undefined'
				const rawPrice = fromPivot ? country.pivot.price : country.price

				let numeric = null
				if (rawPrice != null) {
					const n = Number(rawPrice)
					if (!Number.isNaN(n)) {
						numeric = n
					}
				}

				const display = numeric != null ? format.formatDecimal(numeric) : ''

				if (existing) {
					Object.assign(existing, country, { price: numeric })
				} else {
					const clone = { ...country, price: numeric }
					this.planCountries.push(clone)
				}

				this.countryPriceLastSavedValue[id] = numeric
				this.countryPriceLastSavedDisplay[id] = display
				this.countryPriceInputs[id] = display
			})

			this.sortPlanCountries()
		},

		applyDetachedCountries(countries) {
			if (!Array.isArray(countries) || countries.length === 0) return

			countries.forEach(country => {
				if (!country || typeof country.id === 'undefined') return
				this.detachCountryFromState(country.id)
			})
		},

		onCountryPriceInput(country) {
			if (!country || typeof country.id === 'undefined') return

			const id = country.id
			const raw = this.countryPriceInputs[id]
			const { normalized, display } = format.normalizeDecimalDigitsInput(raw)

			this.countryPriceInputs[id] = display

			let value = normalized
			if (value === '' || value == null) {
				value = null
			}

			country.price = value
			this.scheduleAutosaveCountryPrice(id)
		},

		scheduleAutosaveCountryPrice(countryId) {
			const key = `country:${countryId}`

			if (this.autosaveTimers[key]) {
				clearTimeout(this.autosaveTimers[key])
			}

			this.autosaveTimers[key] = setTimeout(() => {
				this.saveCountryPrice(countryId)
			}, this.autosaveDelay)
		},

		async saveCountryPrice(countryId) {
			const key = `country:${countryId}`
			const country = this.planCountries.find(c => c.id === countryId)
			const value = country ? country.price : null

			try {
				const url = this.route('admin.products.plans.countries.update', {
					product: this.product.id,
					planVersion: this.planVersion.id,
					country: countryId,
				})

				const { data } = await axios.patch(url, { price: value })

				let serverPrice = value
				if (data && data.data && typeof data.data.price !== 'undefined') {
					serverPrice = data.data.price
				}

				let numeric = null
				if (serverPrice != null) {
					const n = Number(serverPrice)
					if (!Number.isNaN(n)) {
						numeric = n
					}
				}

				const display = numeric != null ? format.formatDecimal(numeric) : ''

				this.countryPriceLastSavedValue[countryId] = numeric
				this.countryPriceLastSavedDisplay[countryId] = display

				this.notifyFromResponse(
					data,
					'Precio por país actualizado correctamente.',
					'success',
				)
			} catch (e) {
				const prevDisplay = this.countryPriceLastSavedDisplay[countryId] ?? ''
				const prevValue = this.countryPriceLastSavedValue[countryId] ?? null

				if (country) {

					country.price = prevValue
				}
				this.countryPriceInputs[countryId] = prevDisplay

				this.notifyFromError(e, 'No se pudo guardar el precio para este país.')
			} finally {
				if (this.autosaveTimers[key]) {
					clearTimeout(this.autosaveTimers[key])
					delete this.autosaveTimers[key]
				}
			}
		},

		clearCountryPrice(country) {
			if (!country || typeof country.id === 'undefined') return

			const countryId = country.id
			const key = `country:${countryId}`

			// Si ya está en null y el input vacío, no dispares AJAX
			const lastSaved = this.countryPriceLastSavedValue[countryId] ?? null
			const currentValue = country.price
			const currentInput = this.countryPriceInputs[countryId] ?? ''

			if (lastSaved === null && (currentValue === null || currentValue === undefined) && currentInput === '') {
				return
			}

			if (this.autosaveTimers[key]) {
				clearTimeout(this.autosaveTimers[key])
				delete this.autosaveTimers[key]
			}

			country.price = null
			this.countryPriceInputs[countryId] = ''

			this.saveCountryPrice(countryId)
		},

		confirmDetachCountry(country) {
			if (!country || typeof country.id === 'undefined') return

			const name = (this.translate && this.translate(country.name)) || ''
			const label = name || country.iso2 || 'este país'

			if (typeof window !== 'undefined' && window.confirm) {
				const ok = window.confirm(`¿Quitar ${label} de esta versión de plan?`)
				if (!ok) return
			}

			this.detachCountry(country)
		},

		async detachCountry(country) {
			if (!country || typeof country.id === 'undefined') return

			const countryId = country.id

			try {
				const url = this.route('admin.products.plans.countries.destroy', {
					product: this.product.id,
					planVersion: this.planVersion.id,
					country: countryId,
				})

				const { data } = await axios.delete(url)
				const payload = data || {}
				const removed = payload.data || payload.countries || [country]

				if (Array.isArray(removed)) {
					removed.forEach(c => {
						const id = c && typeof c.id !== 'undefined' ? c.id : countryId
						this.detachCountryFromState(id)
					})
				} else {
					this.detachCountryFromState(countryId)
				}

				this.notifyFromResponse(payload, 'País quitado del plan.')
			} catch (e) {
				this.notifyFromError(e, 'No se pudo quitar el país de la versión.')
			}
		},

		detachCountryFromState(countryId) {
			this.planCountries = this.planCountries.filter(c => c.id !== countryId)

			if (this.countryPriceInputs[countryId] !== undefined) {
				delete this.countryPriceInputs[countryId]
			}
			if (this.countryPriceLastSavedDisplay[countryId] !== undefined) {
				delete this.countryPriceLastSavedDisplay[countryId]
			}
			if (this.countryPriceLastSavedValue[countryId] !== undefined) {
				delete this.countryPriceLastSavedValue[countryId]
			}
		},

		/* =============================================================
		 *            Países permitidos para repatriación
		 * ============================================================= */

		async loadRepatriationCountries() {
			this.isLoadingRepatriationCountries = true

			try {
				const url = this.route('admin.products.plans.repatriation-countries.index', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})

				const { data } = await axios.get(url)
				const raw = data || {}
				const payload = raw && raw.data ? raw.data : raw

				let list = []

				if (Array.isArray(payload.plan_countries)) {
					list = payload.plan_countries
				} else if (Array.isArray(payload.countries)) {
					list = payload.countries.filter(c => c && c.attached)
				} else if (Array.isArray(raw.plan_countries)) {
					list = raw.plan_countries
				} else if (Array.isArray(raw.countries)) {
					list = raw.countries.filter(c => c && c.attached)
				}

				this.repatriationCountries = list.map(country => ({ ...country }))
				this.sortRepatriationCountries()
			} catch (e) {
				this.repatriationCountries = []
				this.notifyFromError(
					e,
					'No se pudieron cargar los países permitidos para repatriación.',
				)
			} finally {
				this.isLoadingRepatriationCountries = false
			}
		},

		openRepatriationCountriesModal() {
			if (this.$refs.repatriationCountriesModal && typeof this.$refs.repatriationCountriesModal.open === 'function') {
				this.$refs.repatriationCountriesModal.open()
			}
		},

		onRepatriationCountriesCreated(countries) {
			this.applyCreatedRepatriationCountries(countries)
		},

		onRepatriationCountriesDetached(countries) {
			this.applyDetachedRepatriationCountries(countries)
		},

		applyCreatedRepatriationCountries(countries) {
			if (!Array.isArray(countries) || countries.length === 0) return

			const byId = new Map(
				(this.repatriationCountries || []).map(country => [country.id, country]),
			)

			countries.forEach(country => {
				if (!country || typeof country.id === 'undefined') return

				const existing = byId.get(country.id)
				if (existing) {
					Object.assign(existing, country)
				} else {
					this.repatriationCountries.push({ ...country })
				}
			})

			this.sortRepatriationCountries()
		},

		applyDetachedRepatriationCountries(countries) {
			if (!Array.isArray(countries) || countries.length === 0) return

			const idsToRemove = new Set(
				countries
					.map(c => c && c.id)
					.filter(id => typeof id !== 'undefined' && id !== null),
			)

			if (!idsToRemove.size) return

			this.repatriationCountries = (this.repatriationCountries || []).filter(
				country => !idsToRemove.has(country.id),
			)
		},

		sortRepatriationCountries() {
			const translate =
				this.translate ||
				(value => (typeof value === 'string' ? value : value?.es || value?.en || ''))

			this.repatriationCountries.sort((a, b) => {
				const nameA = translate(a.name || a.iso2 || '')
				const nameB = translate(b.name || b.iso2 || '')

				return nameA.localeCompare(nameB, undefined, { sensitivity: 'base' })
			})
		},

		async confirmDetachRepatriationCountry(country) {
			if (!country || !country.id) return

			const name = (this.translate && this.translate(country.name)) || ''
			const label = name || country.iso2 || 'este país'

			if (typeof window !== 'undefined' && window.confirm) {
				const ok = window.confirm(
					`¿Quitar ${label} de los países permitidos para repatriación?`,
				)
				if (!ok) return
			}

			await this.detachRepatriationCountry(country)
		},

		async detachRepatriationCountry(country) {
			if (!country || !country.id) return

			try {
				const url = this.route('admin.products.plans.repatriation-countries.destroy', {
					product: this.product.id,
					planVersion: this.planVersion.id,
					country: country.id,
				})

				const { data } = await axios.delete(url)
				const raw = data || {}
				const payload = raw && raw.data ? raw.data : raw
				const countries = Array.isArray(payload.countries)
					? payload.countries
					: [country]

				this.applyDetachedRepatriationCountries(countries)
				this.notifyFromResponse(
					raw,
					'País quitado correctamente de los permitidos para repatriación.',
				)
			} catch (e) {
				this.notifyFromError(
					e,
					'No se pudo quitar el país de los permitidos para repatriación.',
				)
			}
		},

		/* =============================================================
		 *                    Recargos por rango de edad
		 * ============================================================= */

		createAgeSurchargeLocalKey(item = null) {
			if (item && item.id) {
				return `existing-${item.id}`
			}
			return `new-${Date.now()}-${Math.random().toString(36).slice(2)}`
		},

		async loadAgeSurcharges() {
			this.isLoadingAgeSurcharges = true
			try {
				const url = this.route('admin.products.plans.age-surcharges.index', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})

				const { data } = await axios.get(url)
				const payload = data && data.data ? data.data : []

				this.ageSurcharges = (payload || []).map(item => {
					const from = item.age_from != null ? Number(item.age_from) : null
					const to = item.age_to != null ? Number(item.age_to) : null
					const surcharge = item.surcharge_percent != null ? Number(item.surcharge_percent) : 0

					return {
						id: item.id,
						plan_version_id: item.plan_version_id,
						age_from: Number.isNaN(from) ? null : from,
						age_to: Number.isNaN(to) ? null : to,
						surcharge_percent: Number.isNaN(surcharge) ? 0 : surcharge,
						_ageFromInput: from != null && !Number.isNaN(from) ? format.formatInteger(from) : '',
						_ageToInput: to != null && !Number.isNaN(to) ? format.formatInteger(to) : '',
						_surchargeInput: format.formatDecimal(Number.isNaN(surcharge) ? 0 : surcharge),
						_errors: {},
						_autosaveKey: this.createAgeSurchargeLocalKey(item),
					}
				})

				this.recomputeAgeSurchargesValidation()
			} catch (e) {
				this.ageSurcharges = []
				this.notifyFromError(e, 'No se pudieron cargar los recargos por edad.')
			} finally {
				this.isLoadingAgeSurcharges = false
			}
		},

		async addAgeSurchargeRow() {
			try {
				let from = null

				if (this.ageSurcharges.length > 0) {
					const last = this.ageSurcharges[this.ageSurcharges.length - 1]
					if (last && last.age_to != null && !Number.isNaN(Number(last.age_to))) {
						from = Number(last.age_to) + 1
					} else {
						from = null
					}
				}

				const payload = {
					age_from: from,
					age_to: null,
					surcharge_percent: 0,
				}

				const url = this.route('admin.products.plans.age-surcharges.store', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})

				const response = await axios.post(url, payload)
				const data = response.data
				const item = data && data.data ? data.data : null

				if (!item) {
					this.notifyFromResponse(data, 'Rango de edad creado.')
					await this.loadAgeSurcharges()
					return
				}

				const fromVal = item.age_from != null ? Number(item.age_from) : null
				const toVal = item.age_to != null ? Number(item.age_to) : null
				const surchargeVal = item.surcharge_percent != null
					? Number(item.surcharge_percent)
					: 0

				const row = {
					id: item.id,
					plan_version_id: item.plan_version_id,
					age_from: Number.isNaN(fromVal) ? null : fromVal,
					age_to: Number.isNaN(toVal) ? null : toVal,
					surcharge_percent: Number.isNaN(surchargeVal) ? 0 : surchargeVal,
					_ageFromInput: fromVal != null && !Number.isNaN(fromVal)
						? format.formatInteger(fromVal)
						: '',
					_ageToInput: toVal != null && !Number.isNaN(toVal)
						? format.formatInteger(toVal)
						: '',
					_surchargeInput: format.formatDecimal(
						Number.isNaN(surchargeVal) ? 0 : surchargeVal,
					),
					_errors: {},
					_autosaveKey: this.createAgeSurchargeLocalKey(item),
				}

				this.ageSurcharges.push(row)
				this.recomputeAgeSurchargesValidation()
				this.notifyFromResponse(data, 'Rango de edad creado.')
			} catch (e) {
				this.notifyFromError(e, 'No se pudo crear el rango de edad.')
			}
		},

		onAgeFromInput(row) {
			const { value, display } = format.normalizeIntegerInput(row._ageFromInput)
			row._ageFromInput = display
			row.age_from = value
			this.scheduleRecomputeAgeSurchargesValidation()
			this.scheduleAutosaveAgeSurcharge(row, 'age_from')
		},

		onAgeToInput(row) {
			const { value, display } = format.normalizeIntegerInput(row._ageToInput)
			row._ageToInput = display
			row.age_to = value
			this.scheduleRecomputeAgeSurchargesValidation()
			this.scheduleAutosaveAgeSurcharge(row, 'age_to')
		},

		onAgeSurchargeInput(row) {
			const { normalized, display } = format.normalizeDecimalDigitsInput(row._surchargeInput)
			row._surchargeInput = display
			row.surcharge_percent = normalized === '' || normalized == null ? null : normalized
			this.scheduleRecomputeAgeSurchargesValidation()
			this.scheduleAutosaveAgeSurcharge(row, 'surcharge_percent')
		},

		scheduleRecomputeAgeSurchargesValidation() {
			if (this.ageSurchargesValidationTimer) {
				clearTimeout(this.ageSurchargesValidationTimer)
			}

			this.ageSurchargesValidationTimer = setTimeout(() => {
				this.recomputeAgeSurchargesValidation()
				this.ageSurchargesValidationTimer = null
			}, this.autosaveDelay)
		},

		recomputeAgeSurchargesValidation() {
			// Validación por fila
			this.ageSurcharges.forEach(row => {
				const errors = {
					fromRequired: false,
					toRequired: false,
					surchargeRequired: false,
					fromOrder: false,
					toOrder: false,
					overlap: false,
				}

				const hasFrom = row.age_from !== null && row.age_from !== undefined
				const hasTo = row.age_to !== null && row.age_to !== undefined
				const hasSurcharge = row.surcharge_percent !== null &&
					row.surcharge_percent !== undefined &&
					row._surchargeInput !== ''

				if (!hasFrom) errors.fromRequired = true
				if (!hasTo) errors.toRequired = true
				if (!hasSurcharge) errors.surchargeRequired = true

				if (hasFrom && hasTo && Number(row.age_from) > Number(row.age_to)) {
					errors.fromOrder = true
					errors.toOrder = true
				}

				row._errors = errors
			})

			// Validación de solapamiento entre rangos
			const validForOverlap = this.ageSurcharges
				.filter(row => {
					const e = row._errors || {}
					return (
						row.age_from !== null &&
						row.age_to !== null &&
						!e.fromOrder &&
						!e.toOrder
					)
				})
				.slice()

			for (let i = 0; i < validForOverlap.length; i += 1) {
				const a = validForOverlap[i]
				const aFrom = Number(a.age_from)
				const aTo = Number(a.age_to)
				if (Number.isNaN(aFrom) || Number.isNaN(aTo)) continue

				for (let j = i + 1; j < validForOverlap.length; j += 1) {
					const b = validForOverlap[j]
					const bFrom = Number(b.age_from)
					const bTo = Number(b.age_to)
					if (Number.isNaN(bFrom) || Number.isNaN(bTo)) continue

					const overlaps = Math.max(aFrom, bFrom) <= Math.min(aTo, bTo)
					if (overlaps) {
						if (!a._errors) a._errors = {}
						if (!b._errors) b._errors = {}
						a._errors.overlap = true
						b._errors.overlap = true
					}
				}
			}
		},

		hasAgeFromError(row) {
			const e = row._errors || {}
			return !!(e.fromRequired || e.fromOrder || e.overlap)
		},

		hasAgeToError(row) {
			const e = row._errors || {}
			return !!(e.toRequired || e.toOrder)
		},

		hasSurchargeError(row) {
			const e = row._errors || {}
			return !!e.surchargeRequired
		},

		scheduleAutosaveAgeSurcharge(row, field = null) {
			if (!row) return

			if (!row._autosaveKey) {
				row._autosaveKey = this.createAgeSurchargeLocalKey(row)
			}

			const suffix = field || 'all'
			const timerKey = `age-surcharge:${row._autosaveKey}:${suffix}`

			if (this.autosaveTimers[timerKey]) {
				clearTimeout(this.autosaveTimers[timerKey])
			}

			// Guardar siempre, aunque haya campos vacíos (null)
			this.autosaveTimers[timerKey] = setTimeout(() => {
				this.saveAgeSurcharge(row, timerKey, field)
			}, this.autosaveDelay)
		},

		async saveAgeSurcharge(row, timerKey, field = null) {
			try {
				const payload = {}

				if (!field || field === 'age_from') {
					payload.age_from = row.age_from
				}
				if (!field || field === 'age_to') {
					payload.age_to = row.age_to
				}
				if (!field || field === 'surcharge_percent') {
					payload.surcharge_percent = row.surcharge_percent
				}

				let response
				if (row.id) {
					const url = this.route('admin.products.plans.age-surcharges.update', {
						product: this.product.id,
						planVersion: this.planVersion.id,
						ageSurcharge: row.id,
					})
					response = await axios.patch(url, payload)
				} else {
					const url = this.route('admin.products.plans.age-surcharges.store', {
						product: this.product.id,
						planVersion: this.planVersion.id,
					})
					response = await axios.post(url, payload)
				}

				const { data } = response
				const payloadData = data && data.data ? data.data : null

				if (payloadData) {
					row.id = payloadData.id
					row.plan_version_id = payloadData.plan_version_id

					const from = payloadData.age_from != null ? Number(payloadData.age_from) : null
					const to = payloadData.age_to != null ? Number(payloadData.age_to) : null
					const surcharge = payloadData.surcharge_percent != null
						? Number(payloadData.surcharge_percent)
						: row.surcharge_percent

					row.age_from = Number.isNaN(from) ? null : from
					row.age_to = Number.isNaN(to) ? null : to
					row.surcharge_percent = Number.isNaN(surcharge) ? row.surcharge_percent : surcharge

					row._ageFromInput = row.age_from != null ? format.formatInteger(row.age_from) : ''
					row._ageToInput = row.age_to != null ? format.formatInteger(row.age_to) : ''
					row._surchargeInput = row.surcharge_percent != null
						? format.formatDecimal(row.surcharge_percent)
						: ''
				}

				this.recomputeAgeSurchargesValidation()
				this.notifyFromResponse(data, 'Recargo por edad actualizado.')
			} catch (e) {
				this.notifyFromError(e, 'No se pudo guardar el recargo por edad.')
			} finally {
				if (this.autosaveTimers[timerKey]) {
					clearTimeout(this.autosaveTimers[timerKey])
					delete this.autosaveTimers[timerKey]
				}
			}
		},

		async removeAgeSurcharge(row) {
			if (!row) return

			// Si aún no existe en BD, simplemente lo quitamos del estado local
			if (!row.id) {
				this.ageSurcharges = this.ageSurcharges.filter(r => r !== row)
				this.recomputeAgeSurchargesValidation()
				return
			}

			const isEmptyRange =
				(row.age_from === null || row.age_from === undefined) &&
				(row.age_to === null || row.age_to === undefined)

			if (!isEmptyRange && typeof window !== 'undefined' && window.confirm) {
				const ok = window.confirm('¿Eliminar este rango de edad?')
				if (!ok) return
			}

			try {
				const url = this.route('admin.products.plans.age-surcharges.destroy', {
					product: this.product.id,
					planVersion: this.planVersion.id,
					ageSurcharge: row.id,
				})

				const { data } = await axios.delete(url)

				this.ageSurcharges = this.ageSurcharges.filter(r => r.id !== row.id)
				this.recomputeAgeSurchargesValidation()

				this.notifyFromResponse(data, 'Rango de edad eliminado.')
			} catch (e) {
				this.notifyFromError(e, 'No se pudo eliminar el rango de edad.')
			}
		},

		/* =============================================================
		 *                    Producto / versión
		 * ============================================================= */

		openProductModal() {
			if (this.$refs.productModal) {
				this.$refs.productModal.openForEdit(this.product.id)
			}
		},

		onProductUpdated(updated) {
			this.product = { ...this.product, ...updated }
			flash('Producto actualizado correctamente.', 'success')
		},

		// autosave nombre versión
		onVersionNameInput() {
			if (this.versionNameTimer) clearTimeout(this.versionNameTimer)
			this.versionNameTimer = setTimeout(() => {
				this.saveVersionName()
			}, this.autosaveDelay)
		},

		async saveVersionName() {
			try {
				const url = this.route('admin.products.plans.update', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})

				const payload = { name: this.planVersion.name }
				const { data } = await axios.put(url, payload)

				this.planVersion = { ...this.planVersion, ...data.data }
				this.notifyFromResponse(data)
			} catch (e) {
				this.notifyFromError(e, 'No se pudo guardar el nombre de la versión.')
			} finally {
				if (this.versionNameTimer) {
					clearTimeout(this.versionNameTimer)
					this.versionNameTimer = null
				}
			}
		},

		// activar / desactivar
		async setVersionStatus(newStatus) {
			if (this.isTogglingStatus) return

			this.isTogglingStatus = true
			try {
				const url = this.route('admin.products.plans.update', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})

				const payload = { status: newStatus }
				const { data } = await axios.put(url, payload)

				this.planVersion = { ...this.planVersion, ...data.data }
				this.notifyFromResponse(data)
			} catch (e) {
				this.notifyFromError(
					e,
					newStatus === 'active'
						? 'No se pudo activar la versión.'
						: 'No se pudo desactivar la versión.',
				)
			} finally {
				this.isTogglingStatus = false
			}
		},

		activateVersion() {
			if (confirm('¿Desea activar esta versión?')) this.setVersionStatus('active')
		},

		deactivateVersion() {
			if (confirm('¿Desea desactivar esta versión?')) this.setVersionStatus('inactive')
		},

		/* =============================================================
		 *                    Formateo numérico + coberturas
		 * ============================================================= */

		initFormattedNumericFields() {
			this.coverageCategories.forEach(category => {
				category.coverages.forEach(cov => {
					cov._valueIntInput =
						cov.value_int != null ? this.formatInteger(cov.value_int) : ''
					cov._valueDecimalInput =
						cov.value_decimal != null ? this.formatDecimal(cov.value_decimal) : ''

					cov.value_text = {
						es: cov.value_text?.es ?? '',
						en: cov.value_text?.en ?? '',
					}
					cov.notes = {
						es: cov.notes?.es ?? '',
						en: cov.notes?.en ?? '',
					}
				})
			})
		},

		initVersionNumericFields() {
			const pv = this.planVersion || {}

			this.versionIntegerInputs.max_entry_age =
				pv.max_entry_age != null ? format.formatInteger(pv.max_entry_age) : ''
			this.versionIntegerInputs.max_renewal_age =
				pv.max_renewal_age != null ? format.formatInteger(pv.max_renewal_age) : ''
			this.versionIntegerInputs.wtime_suicide =
				pv.wtime_suicide != null ? format.formatInteger(pv.wtime_suicide) : ''
			this.versionIntegerInputs.wtime_preexisting_conditions =
				pv.wtime_preexisting_conditions != null
					? format.formatInteger(pv.wtime_preexisting_conditions)
					: ''
			this.versionIntegerInputs.wtime_accident =
				pv.wtime_accident != null ? format.formatInteger(pv.wtime_accident) : ''

			this.versionDecimalInputs.price_1 =
				pv.price_1 != null ? format.formatDecimal(pv.price_1) : ''
		},

		formatInteger(value) {
			return format.formatInteger(value)
		},

		formatDecimal(value) {
			return format.formatDecimal(value)
		},

		// ENTEROS (coberturas)
		onIntegerInput(cov) {
			const { value, display } = format.normalizeIntegerInput(cov._valueIntInput)
			cov.value_int = value
			cov._valueIntInput = display
			this.scheduleAutosaveCoverage(cov, { value_int: value }, 'value_int')
		},

		// DECIMALES (coberturas)
		onDecimalInput(cov) {
			const { normalized, display } = format.normalizeDecimalDigitsInput(
				cov._valueDecimalInput,
			)

			cov.value_decimal = normalized
			cov._valueDecimalInput = display

			this.scheduleAutosaveCoverage(
				cov,
				{ value_decimal: normalized },
				'value_decimal',
			)
		},

		// ENTEROS (versión)
		onVersionIntegerFieldInput(field) {
			const { value, display } = format.normalizeIntegerInput(
				this.versionIntegerInputs[field],
			)
			this.versionIntegerInputs[field] = display
			this.planVersion[field] = value
			this.scheduleAutosaveVersion({ [field]: value })
		},

		// DECIMALES (versión)
		onVersionDecimalFieldInput(field) {
			const { normalized, display } = format.normalizeDecimalDigitsInput(
				this.versionDecimalInputs[field],
			)
			this.versionDecimalInputs[field] = display
			this.planVersion[field] = normalized
			this.scheduleAutosaveVersion({ [field]: normalized })
		},

		onTranslatableChanged(cov, field) {
			const payload = {}
			payload[field] = cov[field]
			this.scheduleAutosaveCoverage(cov, payload, field)
		},

		// autosave coberturas
		scheduleAutosaveCoverage(cov, payload, keySuffix) {
			const key = `${cov.id}:${keySuffix}`
			if (this.autosaveTimers[key]) clearTimeout(this.autosaveTimers[key])

			this.autosaveTimers[key] = setTimeout(() => {
				this.saveCoverageValue(cov, payload, key)
			}, this.autosaveDelay)
		},

		async saveCoverageValue(cov, payload, key) {
			try {
				const url = this.route('admin.products.plans.coverages.update', {
					product: this.product.id,
					planVersion: this.planVersion.id,
					coverage: cov.id,
				})

				const { data } = await axios.patch(url, payload)
				const updated = data.data

				cov.value_int = updated.value_int
				cov.value_decimal = updated.value_decimal
				cov.value_text = {
					es: updated.value_text?.es ?? '',
					en: updated.value_text?.en ?? '',
				}
				cov.notes = {
					es: updated.notes?.es ?? '',
					en: updated.notes?.en ?? '',
				}

				this.notifyFromResponse(data)
			} catch (e) {
				this.notifyFromError(e, 'No se pudo guardar el cambio en la cobertura.')
			} finally {
				if (this.autosaveTimers[key]) {
					clearTimeout(this.autosaveTimers[key])
					delete this.autosaveTimers[key]
				}
			}
		},

		// autosave versión
		scheduleAutosaveVersion(payload) {
			const fields = Object.keys(payload)
			const key = `version:${fields.join(',')}`

			if (this.autosaveTimers[key]) clearTimeout(this.autosaveTimers[key])

			this.autosaveTimers[key] = setTimeout(() => {
				this.saveVersionFields(payload, key)
			}, this.autosaveDelay)
		},

		async saveVersionFields(payload, key) {
			try {
				const url = this.route('admin.products.plans.update', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})

				const { data } = await axios.put(url, payload)
				this.planVersion = { ...this.planVersion, ...data.data }

				this.notifyFromResponse(data)
			} catch (e) {
				this.notifyFromError(e, 'No se pudo guardar el cambio de la versión.')
			} finally {
				if (this.autosaveTimers[key]) {
					clearTimeout(this.autosaveTimers[key])
					delete this.autosaveTimers[key]
				}
			}
		},

		/* =============================================================
		 *                    Drag & drop coberturas
		 * ============================================================= */

		onCoverageDragStart(categoryId, coverageId, event) {
			this.isDragging = true
			this.dragCategoryId = categoryId
			this.dragCoverageId = coverageId
			this.dragOverCoverageId = coverageId

			if (event && event.dataTransfer) {
				event.dataTransfer.effectAllowed = 'move'
				event.dataTransfer.setData('text/plain', String(coverageId))
			}
		},

		onCoverageDragEnter(categoryId, targetCoverageId) {
			if (!this.isDragging) return
			if (categoryId !== this.dragCategoryId) return

			this.dragOverCoverageId = targetCoverageId
			if (targetCoverageId === this.dragCoverageId) return

			const category = this.coverageCategories.find(c => c.id === categoryId)
			if (!category || !Array.isArray(category.coverages)) return

			const list = category.coverages
			const fromIndex = list.findIndex(c => c.id === this.dragCoverageId)
			const toIndex = list.findIndex(c => c.id === targetCoverageId)

			if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex) return

			const [moved] = list.splice(fromIndex, 1)
			list.splice(toIndex, 0, moved)
		},

		async onCoverageDragEnd() {
			if (!this.isDragging || !this.dragCategoryId) {
				this.resetCoverageDragState()
				return
			}

			const category = this.coverageCategories.find(c => c.id === this.dragCategoryId)
			if (!category || !Array.isArray(category.coverages)) {
				this.resetCoverageDragState()
				return
			}

			const orderedIds = category.coverages.map(c => c.id)

			try {
				const url = this.route('admin.products.plans.coverages.reorder', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})
				const { data } = await axios.post(url, { coverage_ids: orderedIds })
				this.notifyFromResponse(data, null, 'success')
			} catch (e) {
				this.notifyFromError(e, 'No se pudo guardar el nuevo orden.')
			} finally {
				this.resetCoverageDragState()
			}
		},

		resetCoverageDragState() {
			this.isDragging = false
			this.dragCategoryId = null
			this.dragCoverageId = null
			this.dragOverCoverageId = null
		},

		/* =============================================================
		 *                    Añadir / quitar coberturas
		 * ============================================================= */

		async openCoverageSelectorModal() {
			try {
				const url = this.route('admin.products.plans.coverages.available', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})
				const { data } = await axios.get(url)

				const categories = data.data || []
				const rows = []

				categories.forEach(cat => {
					const catName = cat.name
					const catDesc = cat.description

					;(cat.coverages || []).forEach(cov => {
						rows.push({
							id: cov.id,
							coverage_id: cov.id,
							category_id: cat.id,
							category_name: catName,
							category_description: catDesc,
							coverage_name: cov.name,
							coverage_description: cov.description,
							unit_name: cov.unit ? cov.unit.name : null,
							unit_measure_type: cov.unit ? cov.unit.measure_type : null,
							attached: !!cov.attached,
							plan_version_coverage_id: cov.plan_version_coverage_id || null,
						})
					})
				})

				this.availableCoverages = rows

				if (!this.availableCoverages.length) {
					flash('No hay coberturas disponibles para esta versión.', 'info')
					return
				}

				if (this.coverageSelectorModalInstance) {
					this.coverageSelectorModalInstance.show()
				}
			} catch (e) {
				this.availableCoverages = []
				this.notifyFromError(
					e,
					'No se pudo cargar la lista de coberturas disponibles.',
				)
			}
		},

		closeCoverageSelectorModal() {
			if (this.coverageSelectorModalInstance) {
				this.coverageSelectorModalInstance.hide()
			}
		},

		insertCoverageIntoCategories(newCov) {
			const catId = newCov.category_id
			const catSort = newCov.category_sort_order ?? 0

			let category = this.coverageCategories.find(c => c.id === catId)

			if (!category) {
				category = {
					id: catId,
					sort_order: catSort,
					name: newCov.category_name,
					description: newCov.category_description || null,
					coverages: [],
				}

				let insertIndex = this.coverageCategories.findIndex(existing => {
					const existingSort = existing.sort_order ?? 0
					if (existingSort === catSort) return (existing.id ?? 0) > catId
					return existingSort > catSort
				})

				if (insertIndex === -1) this.coverageCategories.push(category)
				else this.coverageCategories.splice(insertIndex, 0, category)
			}

			newCov._valueIntInput =
				newCov.value_int != null ? this.formatInteger(newCov.value_int) : ''
			newCov._valueDecimalInput =
				newCov.value_decimal != null ? this.formatDecimal(newCov.value_decimal) : ''

			newCov.value_text = {
				es: newCov.value_text?.es ?? '',
				en: newCov.value_text?.en ?? '',
			}
			newCov.notes = {
				es: newCov.notes?.es ?? '',
				en: newCov.notes?.en ?? '',
			}

			category.coverages.push(newCov)
		},

		removeCoverageLocally(planVersionCoverageId) {
			this.coverageCategories.forEach(category => {
				category.coverages = category.coverages.filter(
					c => c.id !== planVersionCoverageId,
				)
			})

			this.coverageCategories = this.coverageCategories.filter(
				c => c.coverages.length > 0,
			)
		},

		async attachCoverage(row) {
			try {
				const url = this.route('admin.products.plans.coverages.store', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})

				const { data } = await axios.post(url, { coverage_id: row.id })

				const newCov = data.data
				this.insertCoverageIntoCategories(newCov)

				row.attached = true
				row.plan_version_coverage_id = newCov.id

				this.notifyFromResponse(data)
			} catch (e) {
				this.notifyFromError(e, 'No se pudo añadir la cobertura.')
			}
		},

		async detachCoverage(row) {
			if (!row.plan_version_coverage_id) return

			try {
				const url = this.route('admin.products.plans.coverages.destroy', {
					product: this.product.id,
					planVersion: this.planVersion.id,
					coverage: row.plan_version_coverage_id,
				})

				const { data } = await axios.delete(url)

				this.removeCoverageLocally(row.plan_version_coverage_id)
				row.attached = false
				row.plan_version_coverage_id = null

				this.notifyFromResponse(data)
			} catch (e) {
				this.notifyFromError(e, 'No se pudo quitar la cobertura.')
			}
		},

		async removeCoverageFromVersion(cov) {
			const covName = this.translate(cov.coverage_name)
			const confirmed = window.confirm(
				`¿Seguro que deseas eliminar la cobertura "${covName}" de esta versión?`,
			)
			if (!confirmed) return

			try {
				const url = this.route('admin.products.plans.coverages.destroy', {
					product: this.product.id,
					planVersion: this.planVersion.id,
					coverage: cov.id,
				})

				const { data } = await axios.delete(url)

				this.removeCoverageLocally(cov.id)

				this.availableCoverages.forEach(row => {
					if (row.plan_version_coverage_id === cov.id) {
						row.attached = false
						row.plan_version_coverage_id = null
					}
				})

				this.notifyFromResponse(data)
			} catch (e) {
				this.notifyFromError(
					e,
					'No se pudo eliminar la cobertura de la versión.',
				)
			}
		},

		/* =============================================================
		 *                    Términos HTML (ES / EN)
		 * ============================================================= */

		openTermsHtmlModal(locale) {
			if (this.$refs.termsHtmlModal && typeof this.$refs.termsHtmlModal.open === 'function') {
				this.$refs.termsHtmlModal.open(locale)
			}
		},

		onTermsHtmlUpdated(payload) {
			if (!payload || !payload.locale) return

			const locale = payload.locale
			const html = payload.html || ''
			const bytes = this.getUtf8BytesLength(html)

			this.termsHtmlSizes = {
				...this.termsHtmlSizes,
				[locale]: {
					bytes,
					label: this.formatBytes(bytes),
				},
			}
		},

		async loadTermsHtmlSizes() {
			try {
				const url = this.route('admin.products.plans.terms-html.show', {
					product: this.product.id,
					planVersion: this.planVersion.id,
				})

				const { data } = await axios.get(url)
				const terms = (data && data.data && data.data.terms_html)
					? data.data.terms_html
					: {}

				const locales = ['es', 'en']
				const sizes = { ...this.termsHtmlSizes }

				locales.forEach(locale => {
					const html = terms && typeof terms === 'object' && typeof terms[locale] === 'string'
						? terms[locale]
						: ''

					const bytes = this.getUtf8BytesLength(html || '')
					sizes[locale] = {
						bytes,
						label: this.formatBytes(bytes),
					}
				})

				this.termsHtmlSizes = sizes
			} catch (e) {
				this.notifyFromError(
					e,
					'No se pudo cargar la información de términos y condiciones para calcular el tamaño.',
				)
			}
		},

		getUtf8BytesLength(str) {
			if (!str) return 0

			if (typeof TextEncoder !== 'undefined') {
				return new TextEncoder().encode(str).length
			}

			// Fallback aproximado si TextEncoder no está disponible
			let bytes = 0
			for (let i = 0; i < str.length; i += 1) {
				const codePoint = str.charCodeAt(i)
				if (codePoint <= 0x7F) {
					bytes += 1
				} else if (codePoint <= 0x7FF) {
					bytes += 2
				} else if (codePoint <= 0xFFFF) {
					bytes += 3
				} else {
					bytes += 4
					i += 1
				}
			}
			return bytes
		},

		formatBytes(bytes) {
			if (!bytes || bytes < 0) {
				return '0 B'
			}

			const units = ['B', 'KB', 'MB', 'GB', 'TB']
			let value = bytes
			let unitIndex = 0

			while (value >= 1024 && unitIndex < units.length - 1) {
				value /= 1024
				unitIndex += 1
			}

			if (unitIndex === 0) {
				return `${value} B`
			}

			const rounded = Math.round(value * 10) / 10
			return `${rounded.toFixed(1)} ${units[unitIndex]}`
		},
	},
}
</script>
<style scoped>
	.float-error .invalid-feedback
	{
		position: absolute;
		bottom: -0.8rem;
		left: 0px;
		height: 1rem;
	}
	
	.custom_price .country_name
	{
		background-color: #DFF;
	}
	.custom_price .price_value
	{
		font-weight: bold;
	}
</style>
