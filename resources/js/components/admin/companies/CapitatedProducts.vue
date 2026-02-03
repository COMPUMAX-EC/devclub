<!-- resources/js/components/admin/companies/CapitatedProducts.vue -->
<template>
  <div>
    <!-- Cabecera de empresa -->
    <div class="card mb-7">
      <div
        class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center"
      >
        <div>
          <h2 class="fs-2hx fw-bold mb-2">
            {{ company.name }}
            <span
              v-if="company.status"
              class="badge ms-3"
              :class="statusBadgeClass(company.status)"
            >
              {{ company.status_label || statusLabel(company.status) }}
            </span>
          </h2>

          <div class="text-muted fs-6">
            <span>
              Código:
              <span class="fw-semibold">{{ company.short_code }}</span>
            </span>

            <span v-if="company.phone" class="mx-2">·</span>
            <span v-if="company.phone">
              Teléfono: {{ company.phone }}
            </span>

            <span v-if="company.email" class="mx-2">·</span>
            <span v-if="company.email">
              Email: {{ company.email }}
            </span>
          </div>

          <div v-if="company.description" class="text-muted mt-2">
            {{ company.description }}
          </div>
        </div>

        <div class="mt-4 mt-lg-0">
          <a
            :href="route('admin.companies.edit', { company: company.id })"
            class="btn btn-light-primary btn-sm"
          >
            <i class="bi bi-pencil-square me-2"></i>
            Editar
          </a>
        </div>
      </div>
    </div>

    <!-- Card de batches (global, ancho 100%) -->
    <admin-capitados-batches-card
      v-if="can('capitados.batch.view')"
      :company-id="company.id"
      @batch-applied="onBatchApplied"
    />

    <!-- Card de reportes mensuales -->
    <admin-capitados-monthly-reports-card
      :company-id="company.id"
    />

    <!-- Sección: Productos capitados -->
    <div class="row mt-7">
      <div class="col-12 d-flex justify-content-between align-items-center mb-3">
        <h3 class="h3 mb-0">Planes</h3>
        <button
          type="button"
          class="btn btn-sm btn-primary"
          @click="openCreateProductModal"
        >
          + Crear Plan
        </button>
      </div>
    </div>

    <div class="card-body pt-0">
      <div v-if="products.length === 0" class="text-muted small">
        No hay planes registrados.
      </div>

      <div v-else>
        <!-- Una sub-card por producto -->
        <div
          v-for="product in products"
          :key="product.id"
          class="card mb-6 border"
        >
          <div class="card-header border-0 pt-4 pb-2 d-flex justify-content-between align-items-center">
            <div>
              <h4 class="fw-bold mb-1">
                {{ product.id }} - {{ translate(product.name) }}
              </h4>
            </div>

            <div>
              <div class="input-group input-group-sm">
                <span
                  :class="product.status === 'active' && product.has_active_plan_version ? 'bg-success' : 'bg-secondary'"
                  class="input-group-text"
                  id="inputGroup-sizing-sm"
                >
                  {{ (product.status=== 'active') && product.has_active_plan_version ? 'Activo' : 'Inactivo' }}
                </span>

                <a
                  class="btn btn-sm btn-light-primary"
                  :href="productEditUrl(product)"
                >
                  Gestionar
                </a>
              </div>
            </div>
          </div>

          <div
            class="card-body pt-3"
            v-if="can('capitados.contract.edit') || can('capitados.monthly_record.edit')"
          >
            <!-- Tabla de contratos del producto -->
            <div class="mb-6">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0">Suscripciones</h5>
                <div class="text-muted small"></div>
              </div>

              <admin-capitados-contracts-table
                :company-id="company.id"
                :product-id="product.id"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Modal reutilizado de productos (igual que original) -->
      <admin-products-edit-modal
        ref="productModal"
        forced-product-type="plan_capitado"
        :context-company-id="company.id"
        @created="onProductCreated"
        @updated="onProductUpdated"
      />
    </div>
  </div>
</template>

<script>
export default {
  name: 'AdminCompaniesCapitatedProducts',

  props: {
    company: {
      type: Object,
      required: true,
    },
    initialProducts: {
      type: Array,
      default: () => [],
    },
    editRouteMap: {
      type: Object,
      default: () => ({}),
    },
  },

  data() {
    return {
      products: [],
    };
  },

  created() {
    this.products = JSON.parse(JSON.stringify(this.initialProducts || []));
  },

  methods: {
    onBatchApplied(batch) {
      // Se emite desde BatchesCard SOLO cuando:
      // - terminó en processed (no failed)
      // - total_applied > 0 (al menos 1 aceptado/aplicado)
      // En este punto, se requiere refrescar cards/tabla de planes.
      if (!batch) return;

      const applied = Number(batch?.total_applied ?? 0);
      if (batch.status !== 'processed') return;
      if (applied <= 0) return;

      if (typeof window !== 'undefined') {
        window.location.reload();
      }
    },

    translate(value) {
      if (typeof this.$root.translate === 'function') {
        return this.$root.translate(value);
      }
      if (
        this.$?.appContext?.config?.globalProperties &&
        typeof this.$.appContext.config.globalProperties.translate === 'function'
      ) {
        return this.$.appContext.config.globalProperties.translate(value);
      }
      if (typeof window !== 'undefined' && typeof window.translate === 'function') {
        return window.translate(value);
      }
      return typeof value === 'string' ? value : '';
    },

    statusBadgeClass(status) {
      if (status === 'active') return 'badge-light-success';
      if (status === 'inactive') return 'badge-light-warning';
      if (status === 'archived') return 'badge-light-secondary';
      return 'badge-light';
    },

    statusLabel(status) {
      if (status === 'active') return 'Activa';
      if (status === 'inactive') return 'Suspendida';
      if (status === 'archived') return 'Archivada';
      return status;
    },

    productEditUrl(product) {
      const routeName = this.editRouteMap[product.product_type];
      if (!routeName) {
        // eslint-disable-next-line no-console
        console.warn('Sin editRouteMap para product_type', product.product_type);
        return '#';
      }
      return this.route(routeName, { product: product.id });
    },

    openCreateProductModal() {
      if (this.$refs.productModal && this.$refs.productModal.openForCreate) {
        this.$refs.productModal.openForCreate();
      }
    },

    onProductCreated(newProduct) {
      this.products.unshift(newProduct);
      if (typeof window !== 'undefined' && typeof window.flash === 'function') {
        window.flash('Producto capitado creado correctamente.', 'success');
      }
    },

    onProductUpdated(updatedProduct) {
      const idx = this.products.findIndex((p) => p.id === updatedProduct.id);
      if (idx !== -1) {
        this.products.splice(idx, 1, updatedProduct);
      }
      if (typeof window !== 'undefined' && typeof window.flash === 'function') {
        window.flash('Producto capitado actualizado correctamente.', 'success');
      }
    },

    /**
     * Devuelve:
     * - true: existe versión activa (según datos recibidos del endpoint de capitados)
     * - false: no existe versión activa
     * - null: el endpoint no entregó el dato (no asumimos)
     */
    activePlanVersionState(product) {
      if (!product || typeof product !== 'object') return null;

      if (typeof product.has_active_plan_version === 'boolean') {
        return product.has_active_plan_version;
      }

      if (Object.prototype.hasOwnProperty.call(product, 'active_plan_version_id')) {
        return (
          product.active_plan_version_id !== null &&
          product.active_plan_version_id !== undefined &&
          product.active_plan_version_id !== ''
        );
      }

      return null;
    },

    activePlanVersionLabel(product) {
      const state = this.activePlanVersionState(product);

      if (state === true) {
        const id = product?.active_plan_version_id;
        return id ? `Versión activa: Sí (#${id})` : 'Versión activa: Sí';
      }

      if (state === false) {
        return 'Versión activa: No';
      }

      return 'Versión activa: —';
    },

    activePlanVersionBadgeClass(product) {
      const state = this.activePlanVersionState(product);

      if (state === true) return 'bg-success';
      if (state === false) return 'bg-warning';

      return 'bg-secondary';
    },
  },
};
</script>
