<template>
  <div
    class="modal fade"
    tabindex="-1"
    ref="modal"
  >
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form @submit.prevent="submit">
          <div class="modal-header">
            <h5 class="modal-title">
              {{ isCreateMode ? 'Crear producto' : 'Editar producto' }}
            </h5>
            <button
              type="button"
              class="btn-close"
              :disabled="isSubmitting"
              @click="close"
            ></button>
          </div>

          <div class="modal-body">
            <div v-if="isLoading" class="text-center my-3">
              <div class="spinner-border spinner-border-sm" role="status"></div>
              <span class="ms-2 small text-muted">Cargando datos...</span>
            </div>

            <div v-else>
              <!-- Nombre -->
              <div class="row">
                <div class="mb-3 col-md-6">
                  <label class="form-label">Nombre (ES)</label>
                  <input
                    v-model="form.name.es"
                    type="text"
                    class="form-control"
                    required
                  />
                </div>
                <div class="mb-3 col-md-6">
                  <label class="form-label">Nombre (EN)</label>
                  <input
                    v-model="form.name.en"
                    type="text"
                    class="form-control"
                  />
                </div>
              </div>

              <!-- Descripción -->
              <div class="row">
                <div class="mb-3 col-md-6">
                  <label class="form-label">Descripción (ES)</label>
                  <textarea
                    v-model="form.description.es"
                    rows="2"
                    class="form-control"
                  ></textarea>
                </div>
                <div class="mb-3 col-md-6">
                  <label class="form-label">Descripción (EN)</label>
                  <textarea
                    v-model="form.description.en"
                    rows="2"
                    class="form-control"
                  ></textarea>
                </div>
              </div>

              <!-- Estado (solo en edición) -->
              <div
                class="mb-3"
                v-if="!isCreateMode"
              >
                <label class="form-label">Estado</label>
                <select
                  v-model="form.status"
                  class="form-select"
                  required
                >
                  <option value="inactive">Inactivo</option>
                  <option value="active">Activo</option>
                </select>
              </div>

              <!-- Mostrar en widget (solo productos regulares y solo en edición) -->
              <div class="mb-3" v-if="showWidgetField">
                <label class="form-label">Mostrar en widget de inicio</label>
                <select
                  v-model="form.show_in_widget"
                  class="form-select"
                >
                  <option :value="false">No</option>
                  <option :value="true">Sí</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-sm btn-secondary"
              :disabled="isSubmitting"
              @click="close"
            >
              Cancelar
            </button>
            <button
              type="submit"
              class="btn btn-sm btn-primary"
              :disabled="isSubmitting || isLoading"
            >
              <span
                v-if="isSubmitting"
                class="spinner-border spinner-border-sm me-2"
              ></span>
              Guardar
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminProductsEditModal',

  props: {
    // Ya no se usa para un <select>, pero lo dejamos por compatibilidad
    productTypes: {
      type: Array,
      default: () => [],
    },
    // Tipo de producto fijo según el contexto: "plan_regular" | "plan_capitado"
    forcedProductType: {
      type: String,
      default: null,
    },
    // Para capitados: empresa a la que pertenece el producto
    contextCompanyId: {
      type: [Number, String],
      default: null,
    },
  },

  data() {
    return {
      mode: 'create', // 'create' | 'edit'
      productId: null,

      form: {
        name: { es: '', en: '' },
        description: { es: '', en: '' },
        product_type: '',
        status: 'inactive',
        show_in_widget: false,
      },

      isLoading: false,
      isSubmitting: false,
      modalInstance: null,
    };
  },

  computed: {
    isCreateMode() {
      return this.mode === 'create';
    },

    // Lógica interna:
    // - Nunca mostrar en creación.
    // - En edición solo si el producto NO es capitado.
    showWidgetField() {
      if (this.isCreateMode) {
        return false;
      }

      const type = this.form.product_type || this.forcedProductType || null;

      // En capitados nunca se muestra el campo widget
      if (type === 'plan_capitado') {
        return false;
      }

      // En regulares (u otros futuros), solo en edición
      return true;
    },
  },

  mounted() {
    this.ensureModalInstance();
  },

  methods: {
    ensureModalInstance() {
      if (this.modalInstance) return;
      if (typeof window === 'undefined' || !window.bootstrap) return;
      if (!this.$refs.modal) return;

      const { Modal } = window.bootstrap;
      this.modalInstance = Modal.getOrCreateInstance(this.$refs.modal, {
        backdrop: 'static',
      });

      this.$refs.modal.addEventListener('hidden.bs.modal', () => {
        this.isLoading = false;
        this.isSubmitting = false;
      });
    },

    resetForm() {
      this.form = {
        name: { es: '', en: '' },
        description: { es: '', en: '' },
        product_type: this.forcedProductType || '',
        status: 'inactive',
        show_in_widget: false,
      };
    },

    openForCreate() {
      this.ensureModalInstance();
      this.mode = 'create';
      this.productId = null;
      this.isLoading = false;
      this.isSubmitting = false;
      this.resetForm();

      // Si no viene forcedProductType, intenta tomar el primero de productTypes
      if (!this.form.product_type) {
        const first = (this.productTypes || [])[0];
        if (first && first.value) {
          this.form.product_type = first.value;
        }
      }

      // Siempre inactivo al crear
      this.form.status = 'inactive';
      // Siempre NO en widget al crear (regulares y capitados)
      this.form.show_in_widget = false;

      if (this.modalInstance) {
        this.modalInstance.show();
      }
    },

    async openForEdit(productId) {
      this.ensureModalInstance();
      this.mode = 'edit';
      this.productId = productId;
      this.isSubmitting = false;
      this.resetForm();

      this.isLoading = true;

      try {
        const url = this.route('admin.products.show', { product: productId });
        const { data } = await axios.get(url);
        const product = data.data || data;

        this.form.name = product.name || { es: '', en: '' };
        this.form.description = product.description || { es: '', en: '' };

        // Tipo desde backend (respetando forcedProductType si hiciera falta)
        this.form.product_type =
          product.product_type || this.forcedProductType || '';

        this.form.status = product.status || 'inactive';

        // Para capitados, aunque venga 1 desde BD, lo interpretamos como NO
        const type = this.form.product_type || this.forcedProductType || null;
        if (type === 'plan_capitado') {
          this.form.show_in_widget = false;
        } else {
          this.form.show_in_widget = !!product.show_in_widget;
        }

        if (this.modalInstance) {
          this.modalInstance.show();
        }
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          'No se pudo cargar el producto.';
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(msg, 'danger');
        } else {
          console.error(msg);
        }
      } finally {
        this.isLoading = false;
      }
    },

    close() {
      if (this.modalInstance) {
        this.modalInstance.hide();
      }
    },

    async submit() {
      this.isSubmitting = true;

      // Tipo resuelto (debe existir para crear)
      const resolvedType = this.form.product_type || this.forcedProductType || null;
      const isCapitated = resolvedType === 'plan_capitado';

      // Reglas widget:
      // - En creación: siempre 0.
      // - En capitados: siempre 0 (también en edición).
      // - En regulares en edición: respeta selección.
      const widgetValue =
        this.isCreateMode || isCapitated
          ? 0
          : this.form.show_in_widget
            ? 1
            : 0;

      try {
        let response;

        if (this.isCreateMode) {
          if (!resolvedType) {
            const msg = 'No se ha definido el tipo de producto a crear.';
            if (typeof window !== 'undefined' && typeof window.flash === 'function') {
              window.flash(msg, 'danger');
            } else {
              console.error(msg);
            }
            this.isSubmitting = false;
            return;
          }

          const payload = {
            name: this.form.name,
            description: this.form.description,
            product_type: resolvedType,
            show_in_widget: widgetValue,
            status: 'inactive',
          };

          // Para capitados se asocia a la empresa
          if (this.contextCompanyId) {
            payload.company_id = this.contextCompanyId;
          }

          const url = this.route('admin.products.store');
          response = await axios.post(url, payload);

          const product = response.data.data || response.data;
          this.$emit('created', product);
        } else {
          const payload = {
            name: this.form.name,
            description: this.form.description,
            show_in_widget: widgetValue,
            status: this.form.status,
          };

          const url = this.route('admin.products.update', {
            product: this.productId,
          });
          response = await axios.put(url, payload);

          const product = response.data.data || response.data;
          this.$emit('updated', product);
        }

        this.close();
      } catch (e) {
        let msg =
          e?.response?.data?.message ||
          'Error al guardar el producto.';

        const errors = e?.response?.data?.errors;
        if (errors && typeof errors === 'object') {
          const firstKey = Object.keys(errors)[0];
          const firstVal = firstKey ? errors[firstKey] : null;
          if (Array.isArray(firstVal) && firstVal[0]) {
            msg = firstVal[0];
          }
        }

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(msg, 'danger');
        } else {
          console.error(msg);
        }
      } finally {
        this.isSubmitting = false;
      }
    },
  },
};
</script>
