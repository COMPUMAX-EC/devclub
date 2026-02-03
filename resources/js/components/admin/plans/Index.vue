<!-- /resources/js/components/admin/plans/Index.vue -->
<template>
  <!-- RESUMEN DEL PRODUCTO + BOTÓN EDITAR -->
  <div class="card mb-6">
    <div
      class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-4"
    >
      <div class="flex-grow-1">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
          <h2 class="fs-2 fw-bold mb-0">
            Plan: {{ translate(product.name) || '(Sin nombre)' }}
          </h2>

          <span
            class="badge"
            :class="product.status === 'active' ? 'badge-light-success' : 'badge-light-secondary'"
          >
            {{ product.status === 'active' ? 'Activo' : 'Inactivo' }}
          </span>

          <span
            class="badge"
            :class="product.show_in_widget ? 'badge-light-info' : 'badge-light'"
          >
            {{ product.show_in_widget ? 'Se muestra en home' : 'No se muestra en home' }}
          </span>
        </div>

        <div class="text-muted mb-1">
          ID #{{ product.id }}
          <span v-if="product.product_type">· {{ typeLabel(product.product_type) }}</span>
        </div>

        <div
          v-if="product.description && hasAnyTranslation(product.description)"
          class="text-muted mt-2"
        >
          {{ translate(product.description) }}
        </div>
      </div>

      <div class="text-md-end">
        <button
          type="button"
          class="btn btn-sm btn-outline-primary"
          @click="openProductEditModal"
        >
          Editar datos básicos
        </button>
      </div>
    </div>
  </div>

  <!-- Header listado versiones -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Versiones del plan</h1>

    <button class="btn btn-sm btn-primary" @click="openCreateVersionModal">
      + Crear versión
    </button>
  </div>

  <div class="card">
    <table class="table table-row-dashed table-striped table-hover table-condensed">
      <thead>
        <tr>
          <th style="width: 4rem;">ID</th>
          <th>Nombre interno</th>
          <th>Creada</th>
          <th>Estado</th>
          <th style="width: 6rem;"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="version in versions" :key="version.id">
          <td class="text-muted">#{{ version.id }}</td>
          <td>
            <span class="main" v-if="version.name && version.name.trim().length">
              {{ version.name }}
            </span>
            <span v-else class="text-muted small fst-italic">(Sin nombre)</span>
          </td>
          <td>
            <span class="small text-muted">{{ formatDate(version.created_at) }}</span>
          </td>
          <td>
            <span class="badge" :class="version.status === 'active' ? 'bg-success' : 'bg-secondary'">
              {{ version.status === 'active' ? 'Activa' : 'Inactiva' }}
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
                <li>
                  <a class="dropdown-item" :href="versionEditUrl(version)">Editar</a>
                </li>
                <li>
                  <button type="button" class="dropdown-item" @click="openCloneVersionModal(version)">
                    Clonar
                  </button>
                </li>
                <li v-if="canDeleteVersion(version)">
                  <hr class="dropdown-divider" />
                </li>
                <li v-if="canDeleteVersion(version)">
                  <button type="button" class="dropdown-item text-danger" @click="deleteVersion(version)">
                    Eliminar
                  </button>
                </li>
              </ul>
            </div>
          </td>
        </tr>

        <tr v-if="versions.length === 0">
          <td colspan="5" class="text-muted small">
            No hay versiones registradas para este plan.
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- MODAL CREAR VERSIÓN -->
  <div class="modal fade" tabindex="-1" ref="createVersionModal">
    <div class="modal-dialog">
      <div class="modal-content">
        <form @submit.prevent="submitCreateVersion">
          <div class="modal-header">
            <h5 class="modal-title">Crear nueva versión</h5>
            <button type="button" class="btn-close" @click="closeCreateVersionModal"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info py-2 small mb-3">
              La versión se creará con estado <strong>inactivo</strong>.
              Más adelante definiremos las condiciones mínimas para poder activarla.
            </div>

            <div class="mb-3">
              <label class="form-label">Nombre interno de la versión</label>
              <input type="text" class="form-control" v-model="createVersionName" required>
              <div class="form-text">Sólo visible para administradores. No se usa en el front.</div>
            </div>

            <div v-if="createVersionError" class="alert alert-danger py-2 small">
              {{ createVersionError }}
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary" @click="closeCreateVersionModal">
              Cancelar
            </button>
            <button type="submit" class="btn btn-sm btn-primary">
              Crear y editar versión
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- MODAL CLONAR VERSIÓN -->
  <div class="modal fade" tabindex="-1" ref="cloneVersionModal">
    <div class="modal-dialog">
      <div class="modal-content">
        <form @submit.prevent="submitCloneVersion">
          <div class="modal-header">
            <h5 class="modal-title">
              Clonar versión
              <span v-if="versionToClone">#{{ versionToClone.id }}</span>
            </h5>
            <button type="button" class="btn-close" @click="closeCloneVersionModal"></button>
          </div>
          <div class="modal-body">
            <p class="small mb-3" v-if="versionToClone">
              Origen:
              <strong>{{ versionToClone.name || '(Sin nombre)' }}</strong>
            </p>

            <div class="mb-3">
              <label class="form-label">Nombre interno para el clon</label>
              <input type="text" class="form-control" v-model="cloneVersionName" required>
              <div class="form-text">
                Sólo visible para administradores. El clon también quedará inactivo.
              </div>
            </div>

            <div v-if="cloneVersionError" class="alert alert-danger py-2 small">
              {{ cloneVersionError }}
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-secondary" @click="closeCloneVersionModal">
              Cancelar
            </button>
            <button type="submit" class="btn btn-sm btn-primary">
              Clonar y editar versión
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <admin-products-edit-modal ref="productModal" @updated="onProductUpdated"></admin-products-edit-modal>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminPlansVersionsIndex',

  props: {
    initialProduct: { type: Object, default: () => ({}) },
    initialVersions: { type: Array, default: () => [] },
  },

  data() {
    return {
      product: {},
      versions: [],

      createVersionName: '',
      createVersionError: null,
      createVersionModalInstance: null,

      versionToClone: null,
      cloneVersionName: '',
      cloneVersionError: null,
      cloneVersionModalInstance: null,
    };
  },

  created() {
    this.product = JSON.parse(JSON.stringify(this.initialProduct || {}));
    this.versions = JSON.parse(JSON.stringify(this.initialVersions || []));
  },

  mounted() {
    if (typeof window !== 'undefined' && window.bootstrap) {
      const { Modal } = window.bootstrap;

      if (this.$refs.createVersionModal) {
        this.createVersionModalInstance = Modal.getOrCreateInstance(
          this.$refs.createVersionModal,
          { backdrop: 'static' },
        );
      }

      if (this.$refs.cloneVersionModal) {
        this.cloneVersionModalInstance = Modal.getOrCreateInstance(
          this.$refs.cloneVersionModal,
          { backdrop: 'static' },
        );
      }
    }
  },

  methods: {
    notifyFromResponse(data, fallbackMessage = null, fallbackType = 'success') {
      const toast = data?.toast
      const msg = toast?.message || data?.message || null
      const type = toast?.type || fallbackType

      if (!msg) {
        if (fallbackMessage) flash(fallbackMessage, fallbackType)
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

    hasAnyTranslation(obj) {
      if (!obj || typeof obj !== 'object') return false;
      return Object.values(obj).some(v => !!v);
    },

    formatDate(value) {
      if (!value) return '';
      try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return value;
        return d.toLocaleString();
      } catch (e) {
        return value;
      }
    },

    typeLabel(value) {
      const map = {
        plan_regular: 'Plan regular',
        plan_capitado: 'Plan capitado',
      };
      return map[value] || value;
    },

    versionEditUrl(version) {
      return this.route('admin.products.plans.edit', {
        product: this.product.id,
        planVersion: version.id,
      });
    },

    canDeleteVersion(version) {
      return !!version.is_deletable;
    },

    openProductEditModal() {
      if (this.$refs.productModal && this.product && this.product.id) {
        this.$refs.productModal.openForEdit(this.product.id);
      }
    },

    onProductUpdated(updatedProduct) {
      if (updatedProduct && updatedProduct.id === this.product.id) {
        this.product = JSON.parse(JSON.stringify(updatedProduct));
        flash('Producto actualizado correctamente.', 'success');
      }
    },

    openCreateVersionModal() {
      this.createVersionName = '';
      this.createVersionError = null;
      if (this.createVersionModalInstance) this.createVersionModalInstance.show();
    },

    closeCreateVersionModal() {
      if (this.createVersionModalInstance) this.createVersionModalInstance.hide();
    },

    async submitCreateVersion() {
      this.createVersionError = null;

      const name = (this.createVersionName || '').trim();
      if (!name) {
        this.createVersionError = 'Debes indicar un nombre interno para la versión.';
        return;
      }

      try {
        const url = this.route('admin.products.plans.store', { product: this.product.id });
        const { data } = await axios.post(url, { name });

        if (data.redirect_url) {
          window.location.href = data.redirect_url;
          return;
        }

        if (data.data) this.versions.unshift(data.data);
        this.notifyFromResponse(data, null, 'success');
        this.closeCreateVersionModal();
      } catch (e) {
        this.createVersionError = e.response?.data?.message || 'No se pudo crear la versión.';
        this.notifyFromError(e, this.createVersionError);
      }
    },

    openCloneVersionModal(version) {
      this.versionToClone = version;
      this.cloneVersionError = null;
      this.cloneVersionName = version.name
        ? `${version.name} (copia)`
        : `Copia de versión #${version.id}`;

      if (this.cloneVersionModalInstance) this.cloneVersionModalInstance.show();
    },

    closeCloneVersionModal() {
      if (this.cloneVersionModalInstance) this.cloneVersionModalInstance.hide();
      this.versionToClone = null;
    },

    async submitCloneVersion() {
      this.cloneVersionError = null;

      if (!this.versionToClone) {
        this.cloneVersionError = 'No se ha seleccionado versión a clonar.';
        return;
      }

      const name = (this.cloneVersionName || '').trim();
      if (!name) {
        this.cloneVersionError = 'Debes indicar un nombre interno para el clon.';
        return;
      }

      try {
        const url = this.route('admin.products.plans.clone', {
          product: this.product.id,
          planVersion: this.versionToClone.id,
        });

        const { data } = await axios.post(url, { name });

        if (data.redirect_url) {
          window.location.href = data.redirect_url;
          return;
        }

        if (data.data) this.versions.unshift(data.data);

        this.notifyFromResponse(data, null, 'success');
        this.closeCloneVersionModal();
      } catch (e) {
        this.cloneVersionError = e.response?.data?.message || 'No se pudo clonar la versión.';
        this.notifyFromError(e, this.cloneVersionError);
      }
    },

    async deleteVersion(version) {
      if (!confirm('¿Eliminar esta versión de plan? Esta acción no se puede deshacer.')) return;

      try {
        const url = this.route('admin.products.plans.destroy', {
          product: this.product.id,
          planVersion: version.id,
        });

        const { data } = await axios.delete(url);

        this.versions = this.versions.filter(v => v.id !== version.id);
        this.notifyFromResponse(data, null, 'success');
      } catch (e) {
        this.notifyFromError(e, 'No se pudo eliminar la versión.');
      }
    },
  },
};
</script>
