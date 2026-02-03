<!-- resources/js/components/admin/capitados/ContractsTable.vue -->
<template>
  <div class="position-relative">
    <!-- Indicador flotante de carga -->
    <div
      v-if="loading"
      class="position-absolute top-0 end-0 mt-1 me-2"
      style="z-index: 2"
    >
      <span class="badge bg-light text-muted border">
        Cargando…
      </span>
    </div>

    <div v-if="error" class="text-danger small">
      {{ error }}
    </div>

    <div v-else>
      <!-- Filtro -->
      <div class="mb-3">
        <div class="input-group input-group-sm">
          <span class="input-group-text">
            <i class="bi bi-search"></i>
          </span>

          <input
            v-model="searchText"
            type="text"
            class="form-control"
            placeholder="Buscar por país, persona, documento o contrato…"
            @input="onSearchInput"
          />

          <button
            v-if="searchText"
            type="button"
            class="btn btn-light"
            @click="clearSearch"
          >
            <i class="bi bi-x-lg"></i>
          </button>

          <!-- Filtro por estado -->
          <button
            type="button"
            class="btn btn-light dropdown-toggle"
            data-bs-toggle="dropdown"
            aria-expanded="false"
          >
            {{ statusFilterLabel }}
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li>
              <button
                type="button"
                class="dropdown-item"
                @click="changeStatusFilter('active')"
              >
                Activas
              </button>
            </li>
            <li>
              <button
                type="button"
                class="dropdown-item"
                @click="changeStatusFilter('expired')"
              >
                Expiradas
              </button>
            </li>
            <li>
              <button
                type="button"
                class="dropdown-item"
                @click="changeStatusFilter('voided')"
              >
                Anuladas
              </button>
            </li>
            <li>
              <button
                type="button"
                class="dropdown-item"
                @click="changeStatusFilter('rolled_back')"
              >
                Revertidas
              </button>
            </li>
          </ul>
        </div>
      </div>

      <div v-if="contracts.length === 0" class="text-muted small">
        No hay suscripciones registradas.
      </div>

      <div v-else class="table-responsive">
        <table class="table table-row-dashed table-sm table-condensed table-hover table-striped align-middle mb-3">
          <thead>
            <tr>
              <!-- <th style="width: 4rem;">Contrato</th> -->
              <th>Documento</th>
              <th>Persona</th>
              <th>Residencia</th>
              <th>Repatriación</th>
              <th class="text-center">Desde &rarr; Hasta</th>
              <th></th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="c in contracts"
              :key="c.id"
            >
              <!-- <td class="text-muted">{{ c.id }}</td> -->

              <td>
                {{ c.person?.document_number || '' }}
              </td>
              <td>
                {{ c.person?.full_name || '(Sin nombre)' }}
              </td>

              <td class="text-muted small">
                {{ residenceLabel(c.id) }}
              </td>

              <td class="text-muted small">
                {{ repatriationLabel(c.id) }}
              </td>

              <td class="text-muted small text-center">
                {{ formatDate(c.entry_date) }} &rarr; {{ formatDate(c.valid_until) }}
              </td>
              <td class="text-end">
                <span class="badge me-2" :class="statusBadgeClass(c.status)">
                  {{ statusLabel(c.status) }}
                </span>

                <button
                  type="button"
                  class="btn btn-sm btn-light"
                  @click.stop="openDashboard(c.id)"
                  title="Ver detalle de suscripción"
                >
                  <i class="bi bi-box-arrow-up-right"></i>
                </button>
                <a
                  class="btn btn-sm btn-danger"
				  target="_blank"
                  :href="route('capitated.contracts.show_pdf_by_uuid', c.uuid)"
                  title="Ver detalle de suscripción"
                >
                  <i class="bi bi-file-pdf"></i>
                </a>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Paginación -->
        <div class="d-flex justify-content-between align-items-center small text-muted">
          <span>Total: {{ meta.total }}</span>
          <div>
            <button
              type="button"
              class="btn btn-xs btn-light me-2"
              :disabled="meta.current_page <= 1"
              @click="fetchPage(meta.current_page - 1)"
            >‹</button>

            <button
              type="button"
              class="btn btn-xs btn-light"
              :disabled="meta.current_page >= meta.last_page"
              @click="fetchPage(meta.current_page + 1)"
            >›</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Dashboard de contrato (UNA sola instancia) -->
    <contract-dashboard-modal
      ref="contractDashboard"
      :company-id="companyId"
    />
  </div>
</template>

<script>
import axios from 'axios';
import ContractDashboardModal from './ContractDashboardModal.vue';
import { formatDate as fmtDate } from '../../../utils/format';

export default {
  name: 'ContractsTable',

  components: {
    ContractDashboardModal,
  },

  props: {
    companyId: {
      type: Number,
      required: true,
    },
    productId: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      loading: false,
      error: null,
      contracts: [],
      meta: {
        current_page: 1,
        last_page: 1,
        total: 0,
      },

      // filtro texto
      searchText: '',
      searchDebounceTimer: null,

      // filtro estado (por defecto: activas)
      statusFilter: 'active',

      // cache: último registro mensual por contrato (vía endpoint show)
      lastMonthlyRecordByContractId: {},
      lastMonthlyLoadingByContractId: {},
    };
  },

  computed: {
    statusFilterLabel() {
      if (this.statusFilter === 'active') return 'Activas';
      if (this.statusFilter === 'expired') return 'Expiradas';
      if (this.statusFilter === 'voided') return 'Anuladas';
      if (this.statusFilter === 'rolled_back') return 'Revertidas';
      return 'Estado';
    },
  },

  mounted() {
    this.fetchPage(1);
  },

  beforeDestroy() {
    // Vue 2
    this.clearSearchTimer();
  },

  beforeUnmount() {
    // Vue 3
    this.clearSearchTimer();
  },

  methods: {
    route(name, params = {}) {
      return window.route ? window.route(name, params) : '#';
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

    clearSearchTimer() {
      if (this.searchDebounceTimer) {
        clearTimeout(this.searchDebounceTimer);
        this.searchDebounceTimer = null;
      }
    },

    onSearchInput() {
      // sin fallbacks: si autosaveDelayMs no existe, debe fallar
      if (typeof this.autosaveDelayMs !== 'number') {
        throw new Error('autosaveDelayMs no está definido en globalProperties.');
      }

      this.clearSearchTimer();

      this.searchDebounceTimer = setTimeout(() => {
        this.fetchPage(1);
      }, this.autosaveDelayMs);
    },

    clearSearch() {
      this.searchText = '';
      this.clearSearchTimer();
      this.fetchPage(1);
    },

    changeStatusFilter(status) {
      this.statusFilter = status;
      this.fetchPage(1);
    },

    async fetchPage(page = 1) {
      // sin fallbacks: si perPageMedium no existe, debe fallar
      if (typeof this.perPageMedium !== 'number') {
        throw new Error('perPageMedium no está definido en globalProperties.');
      }

      this.loading = true;
      this.error = null;

      try {
        const url = this.route('admin.companies.capitated.contracts.index', {
          company: this.companyId,
          product_id: this.productId,
          page,
          per_page: this.perPageMedium,
          q: this.searchText,
          status: this.statusFilter,
        });

        const { data } = await axios.get(url);

        // Importante: NO vaciamos contracts antes
        this.contracts = data.data || [];
        this.meta = data.meta || this.meta;

        // cargar (por ajax) el último mes para las filas visibles
        this.fetchLastMonthlyForVisibleContracts(this.contracts);
      } catch (e) {
        this.error = 'No se pudieron cargar las suscripciones.';
      } finally {
        this.loading = false;
      }
    },

    fetchLastMonthlyForVisibleContracts(contracts) {
      for (const c of (contracts || [])) {
        const id = c?.id;
        if (!id) continue;
        this.fetchLastMonthlyRecord(id);
      }
    },

    async fetchLastMonthlyRecord(contractId) {
      if (this.lastMonthlyLoadingByContractId[contractId]) return;

      this.lastMonthlyLoadingByContractId[contractId] = true;

      try {
        const url = this.route('admin.companies.capitated.contracts.show', {
          company: this.companyId,
          contract: contractId,
        });

        const { data } = await axios.get(url);

        // el backend devuelve last_monthly_record con relaciones residenceCountry/repatriationCountry
        this.lastMonthlyRecordByContractId[contractId] = data?.last_monthly_record || null;
      } catch (e) {
        // no rompemos la tabla si falla un contrato puntual, pero dejamos el error en consola
        // eslint-disable-next-line no-console
        console.error(e);
        this.lastMonthlyRecordByContractId[contractId] = null;
      } finally {
        this.lastMonthlyLoadingByContractId[contractId] = false;
      }
    },

    lastMonthlyRecord(contractId) {
      return this.lastMonthlyRecordByContractId[contractId] || null;
    },

    residenceLabel(contractId) {
      const r = this.lastMonthlyRecord(contractId);
      const name = r?.residence_country?.name;
      return name ? this.translate(name) : '';
    },

    repatriationLabel(contractId) {
      const r = this.lastMonthlyRecord(contractId);
      const name = r?.repatriation_country?.name;
      return name ? this.translate(name) : '';
    },

    openDashboard(contractId) {
      if (this.$refs.contractDashboard) {
        this.$refs.contractDashboard.open(contractId);
      }
    },

    statusBadgeClass(status) {
      if (status === 'active') return 'badge-light-success';
      if (status === 'expired') return 'badge-light-warning';
      if (status === 'voided') return 'badge-light-danger';
      return 'badge-light-secondary';
    },

    statusLabel(status) {
      if (status === 'active') return 'Activa';
      if (status === 'expired') return 'Expirada';
      if (status === 'voided') return 'Anulada';
      return status || '';
    },

    formatDate(value) {
      if (!value) return '';
      return fmtDate(value);
    },
  },
};
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
