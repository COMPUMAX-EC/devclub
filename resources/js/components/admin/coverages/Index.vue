<!-- resources/js/components/admin/coverages/Index.vue -->
<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0"></h1>
      <div class="btn-group">
        <button class="btn btn-primary" @click="openCategoryModalForCreate">
          + Crear categoría
        </button>
        <button class="btn bg-body btn-color-gray-700 btn-active-primary" @click="openArchivedCategoriesModal">
          Ver categorías archivadas
        </button>
      </div>
    </div>

    <!-- BLOQUES POR CATEGORÍA -->
    <div
      v-for="category in categories"
      :key="category.id"
      class="mb-4 card"
    >
      <div class="d-flex justify-content-between align-items-center card-header">
        <div>
          <h2 class="h5 mb-1">
            {{ translate(category.name) }}
          </h2>
          <p
            v-if="category.description && hasAnyTranslation(category.description)"
            class="mb-1 text-muted small"
          >
            {{ translate(category.description) }}
          </p>
        </div>

        <!-- Dropdown de acciones de categoría -->
        <div class="btn-group">
          <button
            type="button"
            class="btn btn-sm btn-secondary dropdown-toggle"
            data-bs-toggle="dropdown"
            aria-expanded="false"
          >
            Acciones
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <button class="dropdown-item" @click="openCategoryModalForEdit(category)">
                <i class="fa-solid fa-pencil"></i> Editar
              </button>
            </li>
            <li>
              <button class="dropdown-item" @click="archiveCategory(category)">
                <i class="fa-solid fa-box-archive"></i> Archivar
              </button>
            </li>
            <li>
              <button class="dropdown-item btn-primary" @click="openCoverageModalForCreate(category)">
                <i class="fa-solid fa-plus"></i> Añadir cobertura
              </button>
            </li>
          </ul>
        </div>
      </div>

      <!-- Tabla de coberturas dentro de la categoría -->
      <table class="table table-row-dashed table-striped">
        <thead>
          <tr>
            <th style="width: 2rem;"></th> <!-- handle drag -->
            <th style="width: 50%">Nombre</th>
            <th style="width: 20%">Unidad</th>
            <th style="width: 20%">Estado</th>
            <th></th> <!-- Acciones -->
          </tr>
        </thead>

        <!-- Animación FLIP con transition-group -->
        <transition-group tag="tbody" name="row">
          <tr
            v-for="coverage in (category.coverages || [])"
            :key="coverage.id"
            :class="{
              'table-active': isDragging && dragCategoryId === category.id && dragOverCoverageId === coverage.id,
            }"
            @dragover.prevent
            @dragenter.prevent="onDragEnter(category.id, coverage.id)"
          >
            <td>
              <span
                class="drag-handle"
                draggable="true"
                @dragstart="onDragStart(category.id, coverage.id, $event)"
                @dragend="onDragEnd"
                title="Arrastrar para reordenar"
              >
                <i class="fa-solid fa-up-down"></i>
              </span>
            </td>
            <td>
              <div>
				  <span class="main">{{ translate(coverage.name) }}</span>
                <div
                  v-if="coverage.description && hasAnyTranslation(coverage.description)"
                  class="small text-muted"
                >
                  {{ translate(coverage.description) }}
                </div>
              </div>
            </td>
            <td>
              <span>{{ coverage.unit ? translate(coverage.unit.name) : '-' }}</span>
            </td>
            <td>
              <span
                class="badge"
                :class="coverage.status === 'active' ? 'bg-success' : 'bg-secondary'"
              >
                {{ coverage.status === 'active' ? 'Activa' : 'Archivada' }}
              </span>
            </td>
            <td class="text-end">
              <!-- Dropdown de acciones de cobertura -->
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
                    <button
                      class="dropdown-item"
                      @click="openCoverageModalForEdit(coverage, category)"
                    >
                      Editar
                    </button>
                  </li>
                  <li>
                    <button
                      class="dropdown-item"
                      @click="openCoverageUsagesModal(coverage)"
                    >
                      Ver usos
                    </button>
                  </li>
                  <li><hr class="dropdown-divider" /></li>
                  <li>
                    <button
                      v-if="coverage.status === 'active'"
                      class="dropdown-item text-warning"
                      @click="archiveCoverage(coverage, category)"
                    >
                      Archivar
                    </button>
                    <button
                      v-else
                      class="dropdown-item text-success"
                      @click="restoreCoverage(coverage, category)"
                    >
                      Restaurar
                    </button>
                  </li>
                  <li>
                    <button
                      class="dropdown-item text-danger"
                      @click="deleteCoverage(coverage, category)"
                    >
                      Eliminar
                    </button>
                  </li>
                </ul>
              </div>
            </td>
          </tr>

          <tr
            v-if="!category.coverages || category.coverages.length === 0"
            key="empty-row"
          >
            <td colspan="5" class="text-muted small">
              No hay coberturas en esta categoría.
            </td>
          </tr>
        </transition-group>
      </table>
    </div>

    <!-- MODAL CREAR/EDITAR CATEGORÍA -->
    <div
      class="modal fade"
      tabindex="-1"
      ref="categoryModal"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <form @submit.prevent="submitCategoryForm">
            <div class="modal-header">
              <h5 class="modal-title">
                {{ categoryForm.id ? 'Editar categoría' : 'Crear categoría' }}
              </h5>
              <button type="button" class="btn-close" @click="closeCategoryModal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Nombre (ES)</label>
                <input
                  type="text"
                  class="form-control"
                  v-model="categoryForm.name.es"
                  required
                >
              </div>
              <div class="mb-3">
                <label class="form-label">Nombre (EN)</label>
                <input
                  type="text"
                  class="form-control"
                  v-model="categoryForm.name.en"
                >
              </div>
              <div class="mb-3">
                <label class="form-label">Descripción (ES)</label>
                <textarea
                  class="form-control"
                  rows="2"
                  v-model="categoryForm.description.es"
                ></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Descripción (EN)</label>
                <textarea
                  class="form-control"
                  rows="2"
                  v-model="categoryForm.description.en"
                ></textarea>
              </div>
              <div v-if="categoryFormError" class="alert alert-danger py-2">
                {{ categoryFormError }}
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-secondary"
                @click="closeCategoryModal"
              >
                Cancelar
              </button>
              <button type="submit" class="btn btn-primary">
                Guardar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL CATEGORÍAS ARCHIVADAS -->
    <div
      class="modal fade"
      tabindex="-1"
      ref="archivedCategoriesModal"
    >
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Categorías archivadas</h5>
            <button type="button" class="btn-close" @click="closeArchivedCategoriesModal"></button>
          </div>
          <div class="modal-body">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Descripción</th>
                  <th style="width: 6rem;"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="cat in archivedCategories" :key="cat.id">
                  <td>{{ translate(cat.name) }}</td>
                  <td>
                    <span
                      v-if="cat.description && hasAnyTranslation(cat.description)"
                    >
                      {{ translate(cat.description) }}
                    </span>
                  </td>
                  <td class="text-end">
                    <button
                      class="btn btn-success"
                      @click="restoreCategory(cat)"
                    >
                      Restaurar
                    </button>
                  </td>
                </tr>
                <tr v-if="archivedCategories.length === 0">
                  <td colspan="3" class="text-muted small">
                    No hay categorías archivadas.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="closeArchivedCategoriesModal"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL CREAR/EDITAR COBERTURA -->
    <div
      class="modal fade"
      tabindex="-1"
      ref="coverageModal"
    >
      <div class="modal-dialog">
        <div class="modal-content">
          <form @submit.prevent="submitCoverageForm">
            <div class="modal-header">
              <h5 class="modal-title">
                {{ coverageForm.id ? 'Editar cobertura' : 'Crear cobertura' }}
              </h5>
              <button type="button" class="btn-close" @click="closeCoverageModal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Categoría</label>
                <select class="form-select" v-model="coverageForm.category_id" required>
                  <option
                    v-for="cat in categories"
                    :key="cat.id"
                    :value="cat.id"
                  >
                    {{ translate(cat.name) }}
                  </option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Unidad de medida</label>
                <select class="form-select" v-model="coverageForm.unit_id" required>
                  <option
                    v-for="unit in units"
                    :key="unit.id"
                    :value="unit.id"
                  >
                    {{ translate(unit.name) }} ({{ unit.measure_type }})
                  </option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Nombre (ES)</label>
                <input
                  type="text"
                  class="form-control"
                  v-model="coverageForm.name.es"
                  required
                >
              </div>
              <div class="mb-3">
                <label class="form-label">Nombre (EN)</label>
                <input
                  type="text"
                  class="form-control"
                  v-model="coverageForm.name.en"
                >
              </div>
              <div class="mb-3">
                <label class="form-label">Descripción (ES)</label>
                <textarea
                  class="form-control"
                  rows="2"
                  v-model="coverageForm.description.es"
                ></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Descripción (EN)</label>
                <textarea
                  class="form-control"
                  rows="2"
                  v-model="coverageForm.description.en"
                ></textarea>
              </div>
              <div v-if="coverageFormError" class="alert alert-danger py-2">
                {{ coverageFormError }}
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-secondary"
                @click="closeCoverageModal"
              >
                Cancelar
              </button>
              <button type="submit" class="btn btn-primary">
                Guardar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL VER USOS DE COBERTURA -->
    <div
      class="modal fade"
      tabindex="-1"
      ref="coverageUsagesModal"
    >
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              Usos de cobertura:
              <span v-if="selectedCoverage">
                {{ translate(selectedCoverage.name) }}
              </span>
            </h5>
            <button type="button" class="btn-close" @click="closeCoverageUsagesModal"></button>
          </div>
          <div class="modal-body">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Versión</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="usage in coverageUsages"
                  :key="usage.product_version_id"
                >
                  <td>
                    <template v-if="usage.product_link">
                      <a :href="usage.product_link" target="_blank">
                        {{ usage.product_name || ('Producto #' + usage.product_id) }}
                      </a>
                    </template>
                    <template v-else>
                      {{ usage.product_name || ('Producto #' + usage.product_id) }}
                    </template>
                  </td>
                  <td>
                    <template v-if="usage.version_link">
                      <a :href="usage.version_link" target="_blank">
                        Versión #{{ usage.version_id }}
                      </a>
                    </template>
                    <template v-else>
                      Versión #{{ usage.version_id }}
                    </template>
                  </td>
                  <td></td>
                </tr>
                <tr v-if="coverageUsages.length === 0">
                  <td colspan="3" class="text-muted small">
                    Esta cobertura no está usada en ninguna versión de producto.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              @click="closeCoverageUsagesModal"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios';

export default {
  name: 'AdminCoveragesIndex',
  props: {
    initialCategories: {
      type: Array,
      default: () => [],
    },
    initialUnits: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      categories: JSON.parse(JSON.stringify(this.initialCategories || [])),
      units: JSON.parse(JSON.stringify(this.initialUnits || [])),
      archivedCategories: [],

      // Formularios
      categoryForm: {
        id: null,
        name: { es: '', en: '' },
        description: { es: '', en: '' },
      },
      categoryFormError: null,

      coverageForm: {
        id: null,
        category_id: null,
        unit_id: null,
        name: { es: '', en: '' },
        description: { es: '', en: '' },
      },
      coverageFormError: null,

      // Usos
      selectedCoverage: null,
      coverageUsages: [],

      // Modals
      categoryModalInstance: null,
      archivedCategoriesModalInstance: null,
      coverageModalInstance: null,
      coverageUsagesModalInstance: null,

      // Drag & drop
      isDragging: false,
      dragCategoryId: null,
      dragCoverageId: null,
      dragOverCoverageId: null,
    };
  },
  mounted() {
    if (typeof window !== 'undefined' && window.bootstrap) {
      const { Modal } = window.bootstrap;

      if (this.$refs.categoryModal) {
        this.categoryModalInstance = Modal.getOrCreateInstance(this.$refs.categoryModal, {
          backdrop: 'static',
        });
        this.$refs.categoryModal.addEventListener('hidden.bs.modal', () => {
          this.categoryFormError = null;
        });
      }

      if (this.$refs.archivedCategoriesModal) {
        this.archivedCategoriesModalInstance = Modal.getOrCreateInstance(
          this.$refs.archivedCategoriesModal,
          { backdrop: 'static' },
        );
      }

      if (this.$refs.coverageModal) {
        this.coverageModalInstance = Modal.getOrCreateInstance(this.$refs.coverageModal, {
          backdrop: 'static',
        });
        this.$refs.coverageModal.addEventListener('hidden.bs.modal', () => {
          this.coverageFormError = null;
        });
      }

      if (this.$refs.coverageUsagesModal) {
        this.coverageUsagesModalInstance = Modal.getOrCreateInstance(
          this.$refs.coverageUsagesModal,
          { backdrop: 'static' },
        );
        this.$refs.coverageUsagesModal.addEventListener('hidden.bs.modal', () => {
          this.selectedCoverage = null;
          this.coverageUsages = [];
        });
      }
    }
  },
  methods: {
    // ---- Utilidades ----
    hasAnyTranslation(obj) {
      if (!obj || typeof obj !== 'object') return false;
      return Object.values(obj).some(v => !!v);
    },

    // ---- Categorías ----
    openCategoryModalForCreate() {
      this.categoryForm = {
        id: null,
        name: { es: '', en: '' },
        description: { es: '', en: '' },
      };
      this.categoryFormError = null;
      if (this.categoryModalInstance) {
        this.categoryModalInstance.show();
      }
    },
    openCategoryModalForEdit(category) {
      this.categoryForm = {
        id: category.id,
        name: {
          es: (category.name && category.name.es) || '',
          en: (category.name && category.name.en) || '',
        },
        description: {
          es: (category.description && category.description.es) || '',
          en: (category.description && category.description.en) || '',
        },
      };
      this.categoryFormError = null;
      if (this.categoryModalInstance) {
        this.categoryModalInstance.show();
      }
    },
    closeCategoryModal() {
      if (this.categoryModalInstance) {
        this.categoryModalInstance.hide();
      }
    },
    async submitCategoryForm() {
      this.categoryFormError = null;
      const payload = {
        name: this.categoryForm.name,
        description: this.categoryForm.description,
      };

      try {
        if (this.categoryForm.id) {
          const { data } = await axios.put(
            route('admin.coverages.categories.update', this.categoryForm.id),
            payload,
          );
          const idx = this.categories.findIndex(c => c.id === this.categoryForm.id);
          if (idx !== -1) {
            this.categories[idx] = {
              ...this.categories[idx],
              ...data.data,
            };
          }
          this.flash('Categoría actualizada correctamente', 'success');
        } else {
          const { data } = await axios.post(
            route('admin.coverages.categories.store'),
            payload,
          );
          this.categories.push({ ...data.data, coverages: [] });
          this.flash('Categoría creada correctamente', 'success');
        }
        this.closeCategoryModal();
      } catch (e) {
        this.categoryFormError = e.response?.data?.message || 'Error al guardar la categoría.';
        this.flash(this.categoryFormError, 'danger');
      }
    },
    async archiveCategory(category) {
      if (!confirm('¿Archivar esta categoría?')) return;
      try {
        await axios.post(
          route('admin.coverages.categories.archive', category.id),
        );
        this.categories = this.categories.filter(c => c.id !== category.id);
        this.flash('Categoría archivada', 'success');
      } catch (e) {
        this.flash('No se pudo archivar la categoría.', 'danger');
      }
    },
    async restoreCategory(category) {
      try {
        const { data } = await axios.post(
          route('admin.coverages.categories.restore', category.id),
        );

        let restored = data.data || {};
        if (!Array.isArray(restored.coverages)) {
          restored.coverages = [];
        }

        this.archivedCategories = this.archivedCategories.filter(
          c => c.id !== category.id
        );

        this.categories = this.categories.filter(c => c.id !== restored.id);
        this.categories.push(restored);

        this.flash('Categoría restaurada', 'success');
      } catch (e) {
        this.flash('No se pudo restaurar la categoría.', 'danger');
      }
    },
    async loadArchivedCategories() {
      try {
        const { data } = await axios.get(
          route('admin.coverages.categories.archived'),
        );
        this.archivedCategories = data.data || [];
        return true;
      } catch (e) {
        this.archivedCategories = [];
        this.flash('No se pudieron cargar las categorías archivadas.', 'danger');
        return false;
      }
    },
    async openArchivedCategoriesModal() {
      const ok = await this.loadArchivedCategories();
      if (!ok) {
        return; // si falla el ajax, no abrimos el modal
      }
      if (this.archivedCategoriesModalInstance) {
        this.archivedCategoriesModalInstance.show();
      }
    },
    closeArchivedCategoriesModal() {
      if (this.archivedCategoriesModalInstance) {
        this.archivedCategoriesModalInstance.hide();
      }
    },

    // ---- Coberturas ----
    openCoverageModalForCreate(category) {
      this.coverageForm = {
        id: null,
        category_id: category.id,
        unit_id: this.units[0]?.id || null,
        name: { es: '', en: '' },
        description: { es: '', en: '' },
      };
      this.coverageFormError = null;
      if (this.coverageModalInstance) {
        this.coverageModalInstance.show();
      }
    },
    openCoverageModalForEdit(coverage, category) {
      this.coverageForm = {
        id: coverage.id,
        category_id: category.id,
        unit_id: coverage.unit_id,
        name: {
          es: (coverage.name && coverage.name.es) || '',
          en: (coverage.name && coverage.name.en) || '',
        },
        description: {
          es: (coverage.description && coverage.description.es) || '',
          en: (coverage.description && coverage.description.en) || '',
        },
      };
      this.coverageFormError = null;
      if (this.coverageModalInstance) {
        this.coverageModalInstance.show();
      }
    },
    closeCoverageModal() {
      if (this.coverageModalInstance) {
        this.coverageModalInstance.hide();
      }
    },
    async submitCoverageForm() {
      this.coverageFormError = null;
      const payload = {
        category_id: this.coverageForm.category_id,
        unit_id: this.coverageForm.unit_id,
        name: this.coverageForm.name,
        description: this.coverageForm.description,
      };

      try {
        if (this.coverageForm.id) {
          const { data } = await axios.put(
            route('admin.coverages.items.update', this.coverageForm.id),
            payload,
          );
          this.updateCoverageInCategories(data.data);
          this.flash('Cobertura actualizada correctamente', 'success');
        } else {
          const { data } = await axios.post(
            route('admin.coverages.items.store'),
            payload,
          );
          this.addCoverageToCategory(data.data);
          this.flash('Cobertura creada correctamente', 'success');
        }
        this.closeCoverageModal();
      } catch (e) {
        this.coverageFormError = e.response?.data?.message || 'Error al guardar la cobertura.';
        this.flash(this.coverageFormError, 'danger');
      }
    },
    addCoverageToCategory(newCoverage) {
      const category = this.categories.find(c => c.id === newCoverage.category_id);
      if (!category) return;
      if (!Array.isArray(category.coverages)) category.coverages = [];
      category.coverages.push(newCoverage);
    },
    updateCoverageInCategories(updated) {
      this.categories.forEach(category => {
        if (!Array.isArray(category.coverages)) return;
        const idx = category.coverages.findIndex(c => c.id === updated.id);
        if (idx !== -1) {
          if (category.id !== updated.category_id) {
            category.coverages.splice(idx, 1);
          } else {
            category.coverages[idx] = updated;
          }
        }
      });

      const newCat = this.categories.find(c => c.id === updated.category_id);
      if (newCat && !newCat.coverages?.find(c => c.id === updated.id)) {
        if (!Array.isArray(newCat.coverages)) newCat.coverages = [];
        newCat.coverages.push(updated);
      }
    },
    async archiveCoverage(coverage, category) {
      if (!confirm('¿Archivar esta cobertura?')) return;
      try {
        await axios.post(
          route('admin.coverages.items.archive', coverage.id),
        );
        coverage.status = 'archived';
        this.flash('Cobertura archivada', 'success');
      } catch (e) {
        this.flash('No se pudo archivar la cobertura.', 'danger');
      }
    },
    async restoreCoverage(coverage, category) {
      try {
        const { data } = await axios.post(
          route('admin.coverages.items.restore', coverage.id),
        );
        const idx = category.coverages.findIndex(c => c.id === coverage.id);
        if (idx !== -1) {
          category.coverages[idx] = data.data;
        }
        this.flash('Cobertura restaurada', 'success');
      } catch (e) {
        this.flash('No se pudo restaurar la cobertura.', 'danger');
      }
    },
    async deleteCoverage(coverage, category) {
      if (!confirm('¿Eliminar la cobertura? Si está en uso, se sugerirá archivarla.')) return;
      try {
        await axios.delete(
          route('admin.coverages.items.destroy', coverage.id),
        );
        category.coverages = (category.coverages || []).filter(c => c.id !== coverage.id);
        this.flash('Cobertura eliminada', 'success');
      } catch (e) {
        const msg = e.response?.data?.message || 'No se pudo eliminar la cobertura.';
        this.flash(msg, 'danger');
      }
    },

    // ---- Usos ----
    async openCoverageUsagesModal(coverage) {
      this.selectedCoverage = coverage;
      this.coverageUsages = [];

      if (!this.coverageUsagesModalInstance) {
        return;
      }

      this.coverageUsagesModalInstance.show();

      try {
        const { data } = await axios.get(
          route('admin.coverages.items.usages', coverage.id),
        );
        this.coverageUsages = data.data || [];
      } catch (e) {
        this.coverageUsages = [];
        this.flash('No se pudieron cargar los usos de la cobertura.', 'danger');
      }
    },
    closeCoverageUsagesModal() {
      if (this.coverageUsagesModalInstance) {
        this.coverageUsagesModalInstance.hide();
      }
    },

    // ---- Drag & Drop (live, pero sin temblor) ----
    onDragStart(categoryId, coverageId, event) {
      this.isDragging = true;
      this.dragCategoryId = categoryId;
      this.dragCoverageId = coverageId;
      this.dragOverCoverageId = coverageId;

      if (event && event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(coverageId));
      }
    },

    onDragEnter(categoryId, targetCoverageId) {
      if (!this.isDragging) return;
      if (categoryId !== this.dragCategoryId) return;

      this.dragOverCoverageId = targetCoverageId;

      if (targetCoverageId === this.dragCoverageId) {
        return; // mismo elemento
      }

      const category = this.categories.find(c => c.id === categoryId);
      if (!category || !Array.isArray(category.coverages)) return;

      const list = category.coverages;
      const fromIndex = list.findIndex(c => c.id === this.dragCoverageId);
      const toIndex = list.findIndex(c => c.id === targetCoverageId);

      if (fromIndex === -1 || toIndex === -1 || fromIndex === toIndex) {
        return;
      }

      // Mover elemento en el array; Vue + transition-group se encargan de la animación FLIP
      const [moved] = list.splice(fromIndex, 1);
      list.splice(toIndex, 0, moved);
    },

    async onDragEnd() {
      if (!this.isDragging || !this.dragCategoryId) {
        this.resetDragState();
        return;
      }

      const category = this.categories.find(c => c.id === this.dragCategoryId);
      if (!category || !Array.isArray(category.coverages)) {
        this.resetDragState();
        return;
      }

      const list = category.coverages;

      // Recalcular sort_order final
      list.forEach((cov, idx) => {
        cov.sort_order = idx + 1;
      });

      const payload = {
        items: list.map(c => ({
          id: c.id,
          sort_order: c.sort_order,
        })),
      };

      try {
        await axios.post(
          route('admin.coverages.categories.reorder', this.dragCategoryId),
          payload,
        );
        this.flash('Orden actualizado', 'success');
      } catch (e) {
        this.flash('No se pudo guardar el nuevo orden.', 'danger');
      } finally {
        this.resetDragState();
      }
    },

    resetDragState() {
      this.isDragging = false;
      this.dragCategoryId = null;
      this.dragCoverageId = null;
      this.dragOverCoverageId = null;
    },
  },
};
</script>

<style scoped>
/* Animación FLIP de <transition-group name="row"> */
.row-move {
  transition: transform 0.15s ease;
}
</style>
