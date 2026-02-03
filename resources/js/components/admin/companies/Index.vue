<template>
  <div class="card">
    <div class="card-header border-0 pt-6">
      <div class="card-title d-flex align-items-center">
        <div class="d-flex align-items-center position-relative my-1">
          <span class="svg-icon svg-icon-1 position-absolute ms-4">
            <i class="bi bi-search"></i>
          </span>
          <input
            v-model="filters.search"
            type="text"
            class="form-control form-control-solid w-250px ps-13"
            placeholder="Buscar por nombre, código, teléfono o email"
            @keyup.enter="applyFilters"
          />
        </div>

        <div class="ms-3">
          <select
            v-model="filters.status"
            class="form-select form-select-solid fw-bold"
            style="width: 180px"
            @change="applyFilters"
          >
            <option value="active">Solo activas</option>
            <option value="inactive">Solo suspendidas</option>
            <option value="archived">Solo archivadas</option>
            <option value="all">Todas</option>
          </select>
        </div>

        <button
          type="button"
          class="btn btn-light ms-3"
          @click="resetFilters"
        >
          Limpiar filtros
        </button>
      </div>

      <div class="card-toolbar">
        <button
          type="button"
          class="btn btn-primary"
          @click="openCreateModal"
        >
          <i class="bi bi-plus-lg me-1"></i>
          Nueva empresa
        </button>
      </div>
    </div>

      <div v-if="isLoading" class="text-center py-10">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Cargando...</span>
        </div>
      </div>

      <div v-else>
        <div v-if="companies.length === 0" class="text-center py-10 text-muted">
          No se encontraron empresas con los filtros actuales.
        </div>

        <div v-else>
		<table class="table table-row-dashed table-striped table-hover table-condensed">
			<thead>
              <tr class="text-start text-gray-400 fw-bold fs-7 text-uppercase gs-0">
                <th>ID</th>
                <th>Nombre</th>
                <th>Código</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Estatus</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody class="fw-semibold text-gray-600">
              <tr v-for="company in companies" :key="company.id">
                <td>{{ company.id }}</td>
                <td>{{ company.name }}</td>
                <td>
                  <span class="badge badge-light-primary">
                    {{ company.short_code }}
                  </span>
                </td>
                <td>{{ company.phone || '—' }}</td>
                <td>{{ company.email || '—' }}</td>
                <td>
                  <span
                    class="badge"
                    :class="statusBadgeClass(company.status)"
                  >
                    {{ company.status_label }}
                  </span>
                </td>
                <td class="text-end">
                  <div class="btn-group">
                    <!-- Administrar: va a la vista de administración sin modal -->
                    <a
                      :href="route('admin.companies.capitated-products.index', { company: company.id })"
                      class="btn btn-sm btn-light-primary"
                    >
                      <i class="bi bi-gear"></i>
                      Administrar
                    </a>

                    <!-- Menú de estado -->
                    <button
                      type="button"
                      class="btn btn-sm btn-light-secondary dropdown-toggle dropdown-toggle-split"
                      data-bs-toggle="dropdown"
                      aria-expanded="false"
                    ></button>

                    <ul class="dropdown-menu dropdown-menu-end">
                      <li v-if="company.status === 'active'">
                        <button
                          type="button"
                          class="dropdown-item"
                          @click="suspendCompany(company)"
                        >
                          Suspender
                        </button>
                      </li>
                      <li v-if="company.status === 'inactive'">
                        <button
                          type="button"
                          class="dropdown-item"
                          @click="activateCompany(company)"
                        >
                          Activar
                        </button>
                      </li>
                      <li v-if="company.status !== 'archived'">
                        <button
                          type="button"
                          class="dropdown-item"
                          @click="archiveCompany(company)"
                        >
                          Archivar
                        </button>
                      </li>
                    </ul>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
    </div>

    <!-- Modal ligero (nombre + código), definido en EditModal.vue -->
    <admin-companies-edit-modal
      ref="companyModal"
      @created="onCompanyCreated"
      @updated="onCompanyUpdated"
    ></admin-companies-edit-modal>
  </div>
</template>

<script>
import AdminCompaniesEditModal from './EditModal.vue';

export default {
  name: 'AdminCompaniesIndex',

  components: {
    AdminCompaniesEditModal,
  },

  data() {
    return {
      companies: [],
      filters: {
        search: '',
        status: 'active',
      },
      isLoading: false,
    };
  },

  mounted() {
    this.fetchCompanies();
  },

  methods: {
    async fetchCompanies() {
      this.isLoading = true;

      try {
        const url = this.route('admin.companies.index');
        const { data } = await axios.get(url, {
          params: {
            search: this.filters.search,
            status: this.filters.status,
          },
        });

        this.companies = data.companies || [];
        this.filters = data.filters || this.filters;
      } catch (error) {
        console.error(error);
        this.flash(
          error.response?.data?.message || 'No se pudo cargar la lista de empresas.',
          'danger'
        );
      } finally {
        this.isLoading = false;
      }
    },

    applyFilters() {
      this.fetchCompanies();
    },

    resetFilters() {
      this.filters.search = '';
      this.filters.status = 'active';
      this.fetchCompanies();
    },

    openCreateModal() {
      if (!this.$refs.companyModal) {
        return;
      }
      this.$refs.companyModal.openForCreate();
    },

    statusBadgeClass(status) {
      if (status === 'active') {
        return 'badge-light-success';
      }
      if (status === 'inactive') {
        return 'badge-light-warning';
      }
      if (status === 'archived') {
        return 'badge-light-secondary';
      }
      return 'badge-light';
    },

    async suspendCompany(company) {
      try {
        const url = this.route('admin.companies.suspend', { company: company.id });
        const { data } = await axios.put(url);
        const updated = data.data || data;

        const idx = this.companies.findIndex((c) => c.id === updated.id);
        if (idx !== -1) {
          this.$set(this.companies, idx, updated);
        }

        if (data.toast) {
          this.flash(data.toast.message, data.toast.type);
        } else {
          this.flash('Empresa suspendida correctamente.', 'success');
        }
      } catch (error) {
        console.error(error);
        this.flash(
          error.response?.data?.message || 'No se pudo suspender la empresa.',
          'danger'
        );
      }
    },

    async activateCompany(company) {
      try {
        const url = this.route('admin.companies.activate', { company: company.id });
        const { data } = await axios.put(url);
        const updated = data.data || data;

        const idx = this.companies.findIndex((c) => c.id === updated.id);
        if (idx !== -1) {
          this.$set(this.companies, idx, updated);
        }

        if (data.toast) {
          this.flash(data.toast.message, data.toast.type);
        } else {
          this.flash('Empresa activada correctamente.', 'success');
        }
      } catch (error) {
        console.error(error);
        this.flash(
          error.response?.data?.message || 'No se pudo activar la empresa.',
          'danger'
        );
      }
    },

    async archiveCompany(company) {
      try {
        const url = this.route('admin.companies.archive', { company: company.id });
        const { data } = await axios.put(url);
        const updated = data.data || data;

        const idx = this.companies.findIndex((c) => c.id === updated.id);
        if (idx !== -1) {
          this.$set(this.companies, idx, updated);
        }

        if (data.toast) {
          this.flash(data.toast.message, data.toast.type);
        } else {
          this.flash('Empresa archivada correctamente.', 'success');
        }
      } catch (error) {
        console.error(error);
        this.flash(
          error.response?.data?.message || 'No se pudo archivar la empresa.',
          'danger'
        );
      }
    },

    onCompanyCreated() {
      this.flash('Empresa creada correctamente.', 'success');
      this.fetchCompanies();
    },

    onCompanyUpdated() {
      this.flash('Empresa actualizada correctamente.', 'success');
      this.fetchCompanies();
    },
  },
};
</script>
