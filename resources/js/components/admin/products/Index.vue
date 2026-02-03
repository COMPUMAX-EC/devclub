<!--/resources/js/components/admin/products/Index.vue-->
<template>
  <div class="card">
    <!-- Header -->
    <div class="card-header d-flex justify-content-between align-items-center mb-3">
      <h3 class="h3">Listado de productos (regulares)</h3>
      <button class="btn btn-sm btn-primary" @click="openCreateProductModal">
        + Crear producto
      </button>
    </div>

    <!-- Tabla de productos -->
    <table class="table table-row-dashed table-striped table-hover table-condensed">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Tipo</th>
          <th>Estado</th>
          <th>Mostrar en home</th>
          <th style="width: 7rem;"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="product in products" :key="product.id">
          <td class="text-muted">
            #{{ product.id }}
          </td>
          <td>
            <div>
              <span class="main">{{ translate(product.name) }}</span>
              <div
                v-if="translate(product.description)"
                class="text-muted"
              >
                {{ translate(product.description) }}
              </div>
            </div>
          </td>
          <td>
            {{ typeLabel(product.product_type) }}
          </td>
          <td>
            <span
              class="badge"
              :class="product.status === 'active' ? 'bg-success' : 'bg-secondary'"
            >
              {{ product.status === 'active' ? 'Activo' : 'Inactivo' }}
            </span>
          </td>
          <td>
            <span
              class="badge"
              :class="product.show_in_widget ? 'bg-info' : 'bg-light text-muted border'"
            >
              {{ product.show_in_widget ? 'Sí' : 'No' }}
            </span>
          </td>
          <td class="text-end">
            <div class="btn-group">
              <button
                type="button"
                class="btn btn-sm btn-outline-secondary dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                Acciones
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <!-- Si en el futuro quieres editar datos desde aquí, puedes reactivar esta opción -->
                <!--
                <li>
                  <button
                    type="button"
                    class="dropdown-item"
                    @click="openEditProductModal(product)"
                  >
                    Editar datos
                  </button>
                </li>
                -->
                <li>
                  <a
                    class="dropdown-item"
                    :href="productEditUrl(product)"
                  >
                    Gestionar versiones
                  </a>
                </li>
              </ul>
            </div>
          </td>
        </tr>

        <tr v-if="products.length === 0">
          <td colspan="6" class="text-muted small">
            No hay productos registrados.
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Modal de creación/edición de producto -->
    <admin-products-edit-modal
      ref="productModal"
      :product-types="productTypes"
      @created="onProductCreated"
      @updated="onProductUpdated"
    ></admin-products-edit-modal>
  </div>
</template>

<script>
export default {
  name: 'AdminProductsIndex',

  props: {
    initialProducts: {
      type: Array,
      default: () => [],
    },
    initialProductTypes: {
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
      productTypes: [],
    };
  },

  created() {
    this.products = JSON.parse(JSON.stringify(this.initialProducts || []));
    this.productTypes = JSON.parse(JSON.stringify(this.initialProductTypes || []));
  },

  methods: {
    productEditUrl(product) {
      const routeName = this.editRouteMap[product.product_type];
      if (!routeName) {
        console.warn('Sin editRouteMap para product_type', product.product_type);
        return '#';
      }
      return this.route(routeName, { product: product.id });
    },

    typeLabel(value) {
      const t = this.productTypes.find(pt => pt.value === value);
      return t ? t.label : value;
    },

    openCreateProductModal() {
      if (this.$refs.productModal && this.$refs.productModal.openForCreate) {
        this.$refs.productModal.openForCreate();
      } else {
        console.error('productModal o openForCreate no definido');
      }
    },

    openEditProductModal(product) {
      if (this.$refs.productModal && this.$refs.productModal.openForEdit) {
        this.$refs.productModal.openForEdit(product.id);
      } else {
        console.error('productModal o openForEdit no definido');
      }
    },

    onProductCreated(newProduct) {
      this.products.unshift(newProduct);
      if (typeof window !== 'undefined' && typeof window.flash === 'function') {
        window.flash('Producto creado correctamente.', 'success');
      }
    },

    onProductUpdated(updatedProduct) {
      const idx = this.products.findIndex(p => p.id === updatedProduct.id);
      if (idx !== -1) {
        this.products.splice(idx, 1, updatedProduct);
      }
      if (typeof window !== 'undefined' && typeof window.flash === 'function') {
        window.flash('Producto actualizado correctamente.', 'success');
      }
    },
  },
};
</script>
