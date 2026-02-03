<!-- resources/js/components/admin/plans/RepatriationCountriesModal.vue -->
<template>
	<div
		class="modal fade"
		tabindex="-1"
		ref="modal"
	>
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">
						Países permitidos para repatriación
					</h5>
					<button
						type="button"
						class="btn-close"
						:disabled="isLoading"
						@click="close"
					></button>
				</div>

				<div class="modal-body">
					<!-- Zonas activas -->
					<div class="card mb-4">
						<div class="card-body p-0">
							<div
								v-if="isLoadingZones"
								class="text-center py-3 small text-muted"
							>
								Cargando zonas…
							</div>

							<div
								v-else-if="!zones.length"
								class="px-3 py-3 small text-muted"
							>
								No hay zonas activas configuradas.
							</div>

							<div
								v-else
								class="table-responsive"
							>
								<table class="table table-row-dashed table-hover table-condensed align-middle mb-0">
									<thead>
										<tr>
											<th>Zona</th>
											<th>Países</th>
											<th class="text-end">Acción</th>
										</tr>
									</thead>
									<tbody>
										<tr
											v-for="zone in zones"
											:key="zone.id"
										>
											<td class="main">
												{{ translate(zone.name) }}
											</td>
											<td>
												{{ zone.countries_count }} países
											</td>
											<td class="text-end">
												<div class="btn-group btn-group-sm">
													<button
														type="button"
														class="btn btn-sm btn-light-primary"
														:disabled="processingZoneId === zone.id"
														@click="attachZone(zone)"
													>
														Añadir zona
													</button>
													<button
														type="button"
														class="btn btn-sm btn-light-danger"
														:disabled="processingZoneId === zone.id"
														@click="detachZone(zone)"
													>
														Quitar zona
													</button>
												</div>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<!-- Filtros -->
					<div class="row g-2 mb-3">
						<div class="col-md-5">
							<label class="form-label mb-1">
								Buscar país por nombre
							</label>
							<input
								v-model.trim="filters.search"
								type="search"
								class="form-control form-control-sm"
								placeholder="Nombre del país..."
								@keyup.enter="applyFilters"
							/>
						</div>

						<div class="col-md-3">
							<label class="form-label mb-1">Continente</label>
							<select
								v-model="filters.continent"
								class="form-select form-select-sm"
								@change="applyFilters"
							>
								<option value="">Todos los continentes</option>
								<option
									v-for="(label, code) in continents"
									:key="code"
									:value="code"
								>
									{{ label }}
								</option>
							</select>
						</div>

						<div class="col-md-2">
							<label class="form-label mb-1">Estado país</label>
							<select
								v-model="filters.status"
								class="form-select form-select-sm"
								@change="applyFilters"
							>
								<option value="all">Todos</option>
								<option value="active">Solo activos</option>
								<option value="inactive">Solo inactivos</option>
							</select>
						</div>

						<div class="col-md-2 text-md-end mt-2 mt-md-0">
							<button
								type="button"
								class="btn btn-sm btn-light"
								:disabled="isLoading"
								@click="resetFilters"
							>
								Limpiar filtros
							</button>
						</div>
					</div>

					<!-- Tabla de países -->
					<div v-if="isLoading" class="text-center py-4">
						<div class="spinner-border spinner-border-sm" role="status"></div>
						<span class="ms-2 small text-muted">Cargando países...</span>
					</div>

					<div v-else>
						<div
							v-if="!filteredCountries.length"
							class="alert alert-light text-center"
						>
							No se encontraron países con los filtros seleccionados.
						</div>

						<div
							v-else
							class="table-responsive"
						>
							<table class="table table-row-dashed table-hover table-condensed align-middle mb-0">
								<thead>
									<tr>
										<th>País</th>
										<th style="width: 180px;">Continente</th>
										<th style="width: 120px;">Estado</th>
										<th style="width: 140px;" class="text-end">Acción</th>
									</tr>
								</thead>
								<tbody>
									<tr
										v-for="country in filteredCountries"
										:key="country.id"
									>
										<td class="main">
											{{ translate(country.name) }}
										</td>
										<td>
											{{ country.continent_label || country.continent_code || '—' }}
										</td>
										<td>
											<span
												class="badge"
												:class="country.is_active ? 'bg-success' : 'bg-secondary'"
											>
												{{ country.is_active ? 'Activo' : 'Inactivo' }}
											</span>
										</td>
										<td class="text-end">
											<button
												type="button"
												class="btn btn-sm"
												:class="country.attached ? 'btn-light-danger' : 'btn-success'"
												:disabled="savingCountryId === country.id"
												@click="toggleAttachCountry(country)"
											>
												{{ country.attached ? 'Quitar' : 'Añadir' }}
											</button>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="mt-2 small text-muted">
							Países permitidos para repatriación:
							{{ countries.filter(c => c.attached).length }}
						</div>
					</div>
				</div>

				<div class="modal-footer">
					<button
						type="button"
						class="btn btn-light"
						:disabled="isLoading"
						@click="close"
					>
						Cerrar
					</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import axios from 'axios';

export default {
	name: 'AdminRepatriationCountriesModal',

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

	data() {
		return {
			zones: [],
			countries: [],
			continents: {},
			filters: {
				search: '',
				continent: '',
				status: 'all',
			},
			isLoading: false,        // solo para carga inicial de países
			isLoadingZones: false,   // solo para carga inicial de zonas
			errorMessage: null,
			modalInstance: null,
			filtersDebounceTimer: null,
			savingCountryId: null,   // id del país que se está guardando
			processingZoneId: null,  // id de la zona que se está procesando
		};
	},

	computed: {
		filteredCountries() {
			const search = (this.filters.search || '').toString().toLowerCase();
			const continent = this.filters.continent || '';
			const status = this.filters.status || 'all';

			return this.countries.filter(country => {
				if (continent && country.continent_code !== continent) {
					return false;
				}

				if (status === 'active' && !country.is_active) {
					return false;
				}

				if (status === 'inactive' && country.is_active) {
					return false;
				}

				if (search) {
					// Buscar en TODOS los idiomas que tenga el campo name
					const pieces = [];
					const rawName = country.name;
					const translateFn = this.translate || (v => v);

					// Nombre traducido según la función global (idioma activo)
					const translated = translateFn(rawName);
					if (translated) {
						pieces.push(translated);
					}

					// Todos los valores crudos en name (soporta string, array, objeto)
					if (rawName != null) {
						const type = typeof rawName;

						if (type === 'string' || type === 'number') {
							pieces.push(rawName.toString());
						} else if (Array.isArray(rawName)) {
							rawName.forEach(val => {
								if (val != null && val !== '') {
									pieces.push(String(val));
								}
							});
						} else if (type === 'object') {
							Object.keys(rawName).forEach(key => {
								const val = rawName[key];
								if (val != null && val !== '') {
									pieces.push(String(val));
								}
							});
						}
					}

					const haystack = pieces
						.join(' ')
						.toLowerCase();

					if (!haystack.includes(search)) {
						return false;
					}
				}

				return true;
			});
		},
	},

	methods: {
		ensureModalInstance() {
			if (!this.modalInstance && this.$refs.modal && window.bootstrap?.Modal) {
				this.modalInstance = window.bootstrap.Modal.getOrCreateInstance(this.$refs.modal);
			}
		},

		open() {
			this.ensureModalInstance();
			this.errorMessage = null;
			this.filters.search = '';
			this.filters.continent = '';
			this.filters.status = 'all';
			this.loadData();

			if (this.modalInstance) {
				this.modalInstance.show();
			}
		},

		close() {
			if (this.modalInstance) {
				this.modalInstance.hide();
			}
		},

		async loadData() {
			this.isLoading = true;
			this.isLoadingZones = true;
			this.errorMessage = null;

			try {
				const url = this.route('admin.products.plans.repatriation-countries.index', {
					product: this.productId,
					planVersion: this.planVersionId,
				});
				const { data } = await axios.get(url);

				// backend: { data: { plan_countries, countries, zones, continents } }
				const payload = data && data.data ? data.data : {};

				this.zones = Array.isArray(payload.zones) ? payload.zones : [];
				this.continents = payload.continents || {};

				const list = Array.isArray(payload.countries) ? payload.countries : [];
				this.countries = list.map(country => ({
					...country,
					attached: !!country.attached,
				}));
			} catch (e) {
				console.log('[AdminRepatriationCountriesModal] loadData error', e);
				this.errorMessage =
					e?.response?.data?.message ||
					'No se pudo cargar la información de países.';
				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(this.errorMessage, 'danger');
				}
			} finally {
				this.isLoading = false;
				this.isLoadingZones = false;
			}
		},

		applyFilters() {
			// Los filtros se aplican de forma reactiva en filteredCountries.
		},

		applyFiltersDebounced() {
			if (this.filtersDebounceTimer) {
				clearTimeout(this.filtersDebounceTimer);
			}
			this.filtersDebounceTimer = setTimeout(() => {
				this.applyFilters();
			}, 200);
		},

		resetFilters() {
			this.filters.search = '';
			this.filters.continent = '';
			this.filters.status = 'all';
			this.applyFilters();
		},

		// Contrato backend:
		// { toast: {...}, data: { countries: [ ... ] } }
		parseCountriesFromResponse(body) {
			if (!body || !body.data) return [];
			const payload = body.data;
			if (Array.isArray(payload.countries)) {
				return payload.countries;
			}
			return [];
		},

		async attachZone(zone) {
			if (!zone) return;

			this.processingZoneId = zone.id;
			this.errorMessage = null;

			try {
				const url = this.route('admin.products.plans.repatriation-countries.attach-zone', {
					product: this.productId,
					planVersion: this.planVersionId,
				});

				const { data } = await axios.post(url, {
					zone_id: zone.id,
				});

				const added = this.parseCountriesFromResponse(data);

				if (Array.isArray(added) && added.length) {
					const ids = new Set(added.map(c => c.id));

					this.countries = this.countries.map(country => {
						if (ids.has(country.id)) {
							return { ...country, attached: true };
						}
						return country;
					});

					this.$emit('create', added);
				}

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					const toast = data.toast;
					const msg =
						toast?.message ||
						data.message ||
						'Zona añadida correctamente a la versión.';
					const type = toast?.type || 'success';
					window.flash(msg, type);
				}
			} catch (e) {
				console.log('[AdminRepatriationCountriesModal] attachZone error', e);
				const backendMsg = e?.response?.data?.message || null;
				const msg = backendMsg || 'No se pudo añadir la zona al plan.';

				this.errorMessage = backendMsg || msg;

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(msg, 'danger');
				}
			} finally {
				this.processingZoneId = null;
			}
		},

		async detachZone(zone) {
			if (!zone) return;

			this.processingZoneId = zone.id;
			this.errorMessage = null;

			try {
				const url = this.route('admin.products.plans.repatriation-countries.detach-by-zone', {
					product: this.productId,
					planVersion: this.planVersionId,
				});

				const { data } = await axios.post(url, {
					zone_id: zone.id,
				});

				const removed = this.parseCountriesFromResponse(data);

				if (Array.isArray(removed) && removed.length) {
					const ids = new Set(removed.map(c => c.id));

					this.countries = this.countries.map(country => {
						if (ids.has(country.id)) {
							return { ...country, attached: false };
						}
						return country;
					});

					this.$emit('detach', removed);
				}

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					const toast = data.toast;
					const msg =
						toast?.message ||
						data.message ||
						'Países de la zona quitados correctamente de la versión.';
					const type = toast?.type || 'success';
					window.flash(msg, type);
				}
			} catch (e) {
				console.log('[AdminRepatriationCountriesModal] detachZone error', e);
				const backendMsg = e?.response?.data?.message || null;
				const msg = backendMsg || 'No se pudo quitar la zona del plan.';

				this.errorMessage = backendMsg || msg;

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(msg, 'danger');
				}
			} finally {
				this.processingZoneId = null;
			}
		},

		async toggleAttachCountry(country) {
			if (!country) return;

			const previousAttached = !!country.attached;
			const newAttached = !previousAttached;

			this.errorMessage = null;
			this.savingCountryId = country.id;

			// cambio optimista en la vista
			country.attached = newAttached;

			try {
				if (previousAttached) {
					// antes estaba asociado => ahora lo quitamos
					const url = this.route('admin.products.plans.repatriation-countries.destroy', {
						product: this.productId,
						planVersion: this.planVersionId,
						country: country.id,
					});
					const { data } = await axios.delete(url);

					const removed = this.parseCountriesFromResponse(data);
					if (Array.isArray(removed) && removed.length) {
						this.$emit('detach', removed);
					}

					if (typeof window !== 'undefined' && typeof window.flash === 'function') {
						const toast = data.toast;
						const msg =
							toast?.message ||
							data.message ||
							'País quitado correctamente de la versión.';
						const type = toast?.type || 'success';
						window.flash(msg, type);
					}
				} else {
					// antes NO estaba asociado => ahora lo añadimos
					const url = this.route('admin.products.plans.repatriation-countries.store', {
						product: this.productId,
						planVersion: this.planVersionId,
					});
					const payload = {
						country_ids: [country.id],
					};
					const { data } = await axios.post(url, payload);

					const added = this.parseCountriesFromResponse(data);
					if (Array.isArray(added) && added.length) {
						this.$emit('create', added);
					}

					if (typeof window !== 'undefined' && typeof window.flash === 'function') {
						const toast = data.toast;
						const msg =
							toast?.message ||
							data.message ||
							'Países añadidos correctamente a la versión.';
						const type = toast?.type || 'success';
						window.flash(msg, type);
					}
				}
			} catch (e) {
				console.log('[AdminRepatriationCountriesModal] toggleAttachCountry error', e);
				// revertir estado en caso de error
				country.attached = previousAttached;

				const backendMsg = e?.response?.data?.message || null;
				const msg =
					backendMsg ||
					'No se pudo actualizar la asignación del país.';

				this.errorMessage = backendMsg || msg;

				if (typeof window !== 'undefined' && typeof window.flash === 'function') {
					window.flash(msg, 'danger');
				}
			} finally {
				this.savingCountryId = null;
			}
		},
	},
};
</script>
