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
              {{ isCreateMode ? 'Nueva zona' : 'Editar zona' }}
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
                <label class="form-label">Nombre de la zona</label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  required
                />
              </div>

              <div class="mb-3">
                <label class="form-label">Descripción (opcional)</label>
                <textarea
                  v-model="form.description"
                  class="form-control"
                  rows="3"
                ></textarea>
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
              {{ isCreateMode ? 'Crear zona' : 'Guardar cambios' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AdminZonesEditModal',

  data() {
    return {
      mode: 'create', // 'create' | 'edit'
      zoneId: null,
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
        name: '',
        description: '',
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
      this.zoneId = null;
      this.errorMessage = null;
      this.isSubmitting = false;
      this.isLoading = false;
      this.form = this.emptyForm();

      if (this.modalInstance) {
        this.modalInstance.show();
      }
    },

    async openForEdit(zoneId) {
      this.ensureModalInstance();
      this.mode = 'edit';
      this.zoneId = zoneId;
      this.errorMessage = null;
      this.isSubmitting = false;
      this.isLoading = true;
      this.form = this.emptyForm();

      try {
        const url = this.route('admin.zones.show', { zone: zoneId });
        const { data } = await axios.get(url);
        const zone = data.data || data;

        this.form.name = zone.name || '';
        this.form.description = zone.description || '';
      } catch (e) {
        this.errorMessage =
          e.response?.data?.message || 'No se pudo cargar la zona.';
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
        name: this.form.name,
        description: this.form.description || null,
      };

      try {
        if (this.isCreateMode) {
          const url = this.route('admin.zones.store');
          await axios.post(url, payload);
          this.$emit('created');
        } else {
          const url = this.route('admin.zones.update', {
            zone: this.zoneId,
          });
          await axios.put(url, payload);
          this.$emit('updated');
        }

        this.close();
      } catch (e) {
        this.errorMessage =
          e.response?.data?.message || 'Error al guardar la zona.';
      } finally {
        this.isSubmitting = false;
      }
    },
  },
};
</script>
