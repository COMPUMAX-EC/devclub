<!-- /resources/js/components/admin/countries/EditModal.vue -->
<template>
  <div
    class="modal fade"
    tabindex="-1"
    ref="modal"
  >
    <div class="modal-dialog">
      <div class="modal-content">
        <form @submit.prevent="submit">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ isCreateMode ? 'Nuevo país' : 'Editar país' }}
            </h5>
            <button
              type="button"
              class="btn-close"
              :disabled="isSubmitting"
              @click="close"
            ></button>
          </div>

          <div class="modal-body">
            <div v-if="errorMessage" class="alert alert-danger py-2">
              {{ errorMessage }}
            </div>

            <div v-if="isLoading" class="text-center my-3">
              <div class="spinner-border spinner-border-sm" role="status"></div>
              <span class="ms-2 small text-muted">Cargando datos...</span>
            </div>

            <div v-else>
              <div class="mb-3">
                <label class="form-label">Nombre en español</label>
                <input
                  v-model="form.name.es"
                  type="text"
                  class="form-control"
                  required
                />
              </div>

              <div class="mb-3">
                <label class="form-label">Nombre en inglés</label>
                <input
                  v-model="form.name.en"
                  type="text"
                  class="form-control"
                  required
                />
              </div>

              <div class="row">
                <div class="mb-3 col-md-6">
                  <label class="form-label">Código ISO 2 letras</label>
                  <input
                    v-model="form.iso2"
                    type="text"
                    class="form-control"
                    maxlength="2"
                    @blur="form.iso2 = form.iso2 ? form.iso2.toUpperCase() : ''"
                    required
                  />
                  <div class="form-text">
                    Ejemplo: CL, US, AR
                  </div>
                </div>

                <div class="mb-3 col-md-6">
                  <label class="form-label">Código ISO 3 letras</label>
                  <input
                    v-model="form.iso3"
                    type="text"
                    class="form-control"
                    maxlength="3"
                    @blur="form.iso3 = form.iso3 ? form.iso3.toUpperCase() : ''"
                    required
                  />
                  <div class="form-text">
                    Ejemplo: CHL, USA, ARG
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Continente</label>
                <select
                  v-model="form.continent_code"
                  class="form-select"
                  required
                >
                  <option value="" disabled>Selecciona un continente</option>
                  <option
                    v-for="(label, code) in continents"
                    :key="code"
                    :value="code"
                  >
                    {{ label }}
                  </option>
                </select>
              </div>

              <div class="mb-0">
                <label class="form-label">Código telefónico (opcional)</label>
                <div class="input-group">
                  <span class="input-group-text">+</span>
                  <input
                    v-model="form.phone_code"
                    type="text"
                    class="form-control"
                    inputmode="numeric"
                    pattern="[0-9]*"
                  />
                </div>
                <div class="form-text">
                  Solo dígitos, sin el signo +. Puede quedar vacío.
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-light"
              :disabled="isSubmitting"
              @click="close"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="isSubmitting || isLoading"
            >
              <span
                v-if="isSubmitting"
                class="spinner-border spinner-border-sm me-2"
                role="status"
              ></span>
              {{ isCreateMode ? 'Crear país' : 'Guardar cambios' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AdminCountriesEditModal',

  props: {
    continents: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      mode: 'create', // 'create' | 'edit'
      countryId: null,
      form: this.emptyForm(),
      isLoading: false,
      isSubmitting: false,
      errorMessage: null,
      modalInstance: null,
    };
  },

  computed: {
    isCreateMode() {
      return this.mode === 'create';
    },
  },

  methods: {
    emptyForm() {
      return {
        name: {
          es: '',
          en: '',
        },
        iso2: '',
        iso3: '',
        continent_code: '',
        phone_code: '',
      };
    },

    ensureModalInstance() {
      if (!this.modalInstance && this.$refs.modal) {
        this.modalInstance = new window.bootstrap.Modal(this.$refs.modal);
      }
    },

    openForCreate() {
      this.ensureModalInstance();
      this.mode = 'create';
      this.countryId = null;
      this.errorMessage = null;
      this.isSubmitting = false;
      this.isLoading = false;
      this.form = this.emptyForm();

      if (this.modalInstance) {
        this.modalInstance.show();
      }
    },

    async openForEdit(countryId) {
      this.ensureModalInstance();
      this.mode = 'edit';
      this.countryId = countryId;
      this.errorMessage = null;
      this.isSubmitting = false;
      this.isLoading = true;
      this.form = this.emptyForm();

      try {
        const url = this.route('admin.countries.show', { country: countryId });
        const { data } = await axios.get(url);
        const country = data.data || data;

        this.form.name.es = country.name?.es || '';
        this.form.name.en = country.name?.en || '';
        this.form.iso2 = country.iso2 || '';
        this.form.iso3 = country.iso3 || '';
        this.form.continent_code = country.continent_code || '';
        this.form.phone_code = country.phone_code || '';
      } catch (e) {
        this.errorMessage =
          e.response?.data?.message || 'No se pudo cargar el país.';
      } finally {
        this.isLoading = false;
      }

      if (this.modalInstance) {
        this.modalInstance.show();
      }
    },

    close() {
      if (this.modalInstance) {
        this.modalInstance.hide();
      }
    },

    async submit() {
      this.errorMessage = null;
      this.isSubmitting = true;

      const payload = {
        name: {
          es: this.form.name.es,
          en: this.form.name.en,
        },
        iso2: this.form.iso2 ? this.form.iso2.toUpperCase() : '',
        iso3: this.form.iso3 ? this.form.iso3.toUpperCase() : '',
        continent_code: this.form.continent_code,
        phone_code: this.form.phone_code || null,
      };

      try {
        if (this.isCreateMode) {
          const url = this.route('admin.countries.store');
          await axios.post(url, payload);
          this.$emit('created');
        } else {
          const url = this.route('admin.countries.update', {
            country: this.countryId,
          });
          await axios.put(url, payload);
          this.$emit('updated');
        }

        this.close();
      } catch (e) {
        this.errorMessage =
          e.response?.data?.message || 'Error al guardar el país.';
      } finally {
        this.isSubmitting = false;
      }
    },
  },
};
</script>
