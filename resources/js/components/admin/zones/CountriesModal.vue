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
            Editar países de la zona
          </h5>
          <button
            type="button"
            class="btn-close"
            :disabled="isLoading"
            @click="close"
          ></button>
        </div>

        <div class="modal-body">
          <div v-if="errorMessage" class="alert alert-danger py-2">
            {{ errorMessage }}
          </div>

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

          <div v-if="isLoading" class="text-center py-4">
            <div class="spinner-border spinner-border-sm" role="status"></div>
            <span class="ms-2 small text-muted">Cargando países...</span>
          </div>

          <div v-else>
            <div v-if="countries.length === 0" class="alert alert-light text-center">
              No se encontraron países con los filtros seleccionados.
            </div>

            <div v-else class="table-responsive">
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
                  <tr v-for="country in countries" :key="country.id">
                    <td>
                      {{ translate(country.name) }}
                    </td>
                    <td>
                      {{ country.continent_label }}
                    </td>
                    <td>
                      <span
                        class="badge"
                        :class="
                          country.is_active
                            ? 'bg-success'
                            : 'bg-secondary'
                        "
                      >
                        {{ country.is_active ? 'Activo' : 'Inactivo' }}
                      </span>
                    </td>
                    <td class="text-end">
                      <button
                        type="button"
                        class="btn btn-sm"
                        :class="
                          country.attached
                            ? 'btn-light-danger'
                            : 'btn-success'
                        "
                        @click="toggleAttach(country)"
                      >
                        {{ country.attached ? 'Quitar' : 'Añadir' }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-2 small text-muted">
              Países incluidos en la zona:
              {{
                countries.filter((c) => c.attached).length
              }}
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
export default {
  name: 'AdminZonesCountriesModal',

  props: {
    continents: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      zoneId: null,
      countries: [],
      filters: {
        search: '',
        continent: '',
        status: 'active',
      },
      isLoading: false,
      errorMessage: null,
      modalInstance: null,
    };
  },

  methods: {
    ensureModalInstance() {
      if (!this.modalInstance && this.$refs.modal) {
        this.modalInstance = new window.bootstrap.Modal(this.$refs.modal);
      }
    },

    openForZone(zoneId) {
      this.ensureModalInstance();
      this.zoneId = zoneId;
      this.filters.search = '';
      this.filters.continent = '';
      this.filters.status = 'active';
      this.errorMessage = null;
      this.countries = [];
      this.loadCountries();

      if (this.modalInstance) {
        this.modalInstance.show();
      }
    },

    close() {
      if (this.modalInstance) {
        this.modalInstance.hide();
      }
    },

    async loadCountries() {
      if (!this.zoneId) return;

      this.isLoading = true;

      const params = {
        search: this.filters.search || undefined,
        continent: this.filters.continent || undefined,
        status: this.filters.status || 'active',
      };

      try {
        const url = this.route('admin.zones.countries.available', {
          zone: this.zoneId,
        });
        const { data } = await axios.get(url, { params });

        this.countries = data.countries || [];

        if (data.filters) {
          this.filters.search = data.filters.search ?? this.filters.search;
          this.filters.continent =
            data.filters.continent ?? this.filters.continent;
          this.filters.status = data.filters.status ?? this.filters.status;
        }
      } catch (e) {
        this.errorMessage =
          e.response?.data?.message || 'No se pudo cargar la lista de países.';
      } finally {
        this.isLoading = false;
      }
    },

    applyFilters() {
      this.loadCountries();
    },

    resetFilters() {
      this.filters.search = '';
      this.filters.continent = '';
      this.filters.status = 'active';
      this.loadCountries();
    },

    async toggleAttach(country) {
      if (!this.zoneId) return;

      const attached = !!country.attached;

      try {
        if (attached) {
          const url = this.route('admin.zones.countries.detach', {
            zone: this.zoneId,
            country: country.id,
          });
          const { data } = await axios.delete(url);
          this.flash(data.message || 'País quitado de la zona.', 'success');
          country.attached = false;
        } else {
          const url = this.route('admin.zones.countries.attach', {
            zone: this.zoneId,
            country: country.id,
          });
          const { data } = await axios.post(url);
          this.flash(data.message || 'País añadido a la zona.', 'success');
          country.attached = true;
        }

        this.$emit('updated');
      } catch (e) {
        const msg =
          e.response?.data?.message ||
          'No se pudo actualizar la asignación del país.';
        this.flash(msg, 'danger');
      }
    },
  },
};
</script>
