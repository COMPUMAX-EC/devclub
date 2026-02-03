<!-- resources/js/components/admin/capitados/ContractDashboardModal.vue -->
<template>
  <div
    class="modal fade"
    tabindex="-1"
    ref="modalEl"
    aria-hidden="true"
  >
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            Contrato
            <span v-if="contract?.id">#{{ contract.id }}</span>
          </h5>
          <button
            type="button"
            class="btn-close"
            aria-label="Cerrar"
            @click="close"
          ></button>
        </div>

        <div class="modal-body">
          <!-- Estado de carga / error -->
          <div v-if="loading" class="text-muted small mb-3">
            Cargando información del contrato…
          </div>

          <div v-else-if="error" class="text-danger small mb-3">
            {{ error }}
          </div>

          <div v-else-if="!contract" class="text-muted small mb-3">
            No se pudo cargar la información del contrato.
          </div>

          <div v-else>
            <!-- ==========================
                 1) FICHA DE LA PERSONA
                 ========================== -->
            <section class="mb-4">
              <h6 class="fw-bold mb-3 border-bottom pb-2">
                Ficha de la persona
              </h6>

              <div class="row">
                <div class="col-md-8 mb-3">
                  <div class="text-muted small">
                    Nombre
                  </div>
                  <div class="fw-semibold fs-5">
                    {{ contract.person?.full_name || '(Sin nombre)' }}
                  </div>
                </div>

                <div class="col-md-4 mb-3 text-md-end">
                  <div class="text-muted small">
                    Documento
                  </div>
                  <div class="fw-semibold">
                    {{ contract.person?.document_number || '-' }}
                  </div>
                </div>
              </div>

              <div class="row small">
                <div class="col-md-4 mb-2">
                  <div class="text-muted">
                    Sexo
                  </div>
                  <div class="fw-semibold">
                    {{ sexLabel(contract.person?.sex) || '-' }}
                  </div>
                </div>
              </div>
            </section>

            <!-- ==========================
                 2) DATOS DEL CONTRATO
                 ========================== -->
            <section class="mb-4">
              <h6 class="fw-bold mb-3 border-bottom pb-2">
                Contrato
              </h6>

              <div class="row small mb-3">
                <div class="col-md-4 mb-2">
                  <div class="text-muted">
                    Contrato
                  </div>
                  <div class="fw-semibold">
                    #{{ contract.id }}
                  </div>
                </div>

                <div class="col-md-4 mb-2">
                  <div class="text-muted">
                    Estado
                  </div>
                  <div>
                    <span class="badge" :class="statusBadgeClass(contract.status)">
                      {{ statusLabel(contract.status) }}
                    </span>
                  </div>
                </div>

                <div class="col-md-4 mb-2">
                  <div class="text-muted">
                    Producto
                  </div>
                  <div class="fw-semibold">
                    <span v-if="contract.product">
                      {{ translate(contract.product.name) }}
                    </span>
                    <span v-else>-</span>
                  </div>
                </div>
              </div>

              <div class="row small mb-3">
                <div class="col-md-4 mb-2">
                  <div class="text-muted">
                    Vigencia
                  </div>
                  <div class="fw-semibold">
                    <div v-if="contract.entry_date">
                      Desde: {{ formatDate(contract.entry_date) }}
                    </div>
                    <div v-if="contract.valid_until">
                      Hasta: {{ formatDate(contract.valid_until) }}
                    </div>
                    <div v-if="!contract.entry_date && !contract.valid_until">
                      -
                    </div>
                  </div>
                </div>

                <div class="col-md-4 mb-2">
                  <div class="text-muted">
                    Edad de entrada
                  </div>
                  <div class="fw-semibold">
                    <span v-if="contract.entry_age !== null && contract.entry_age !== undefined">
                      {{ contract.entry_age }} años
                    </span>
                    <span v-else>-</span>
                  </div>
                </div>
              </div>

              <div class="row small">
                <div class="col-md-6 mb-2">
                  <div class="text-muted">
                    Fecha de alta
                  </div>
                  <div class="fw-semibold">
                    <span v-if="contract.created_at">
                      {{ formatDateTime(contract.created_at) }}
                    </span>
                    <span v-else>-</span>
                  </div>
                </div>

                <div class="col-md-6 mb-2">
                  <div class="text-muted">
                    Última actualización
                  </div>
                  <div class="fw-semibold">
                    <span v-if="contract.updated_at">
                      {{ formatDateTime(contract.updated_at) }}
                    </span>
                    <span v-else>-</span>
                  </div>
                </div>
              </div>
            </section>

            <!-- ==========================
                 3) ÚLTIMO MES REGISTRADO
                 ========================== -->
            <section>
              <h6 class="fw-bold mb-3 border-bottom pb-2">
                Último mes registrado
              </h6>

              <div v-if="!lastMonthlyRecord" class="small text-muted">
                No hay registros mensuales asociados a este contrato.
              </div>

              <div v-else class="small">
                <div class="row mb-3">
                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Nombre
                    </div>
                    <div class="fw-semibold">
                      {{ lastMonthlyRecord.full_name || '-' }}
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Sexo
                    </div>
                    <div class="fw-semibold">
                      {{ sexLabel(lastMonthlyRecord.sex) || '-' }}
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Edad reportada
                    </div>
                    <div class="fw-semibold">
                      <span v-if="lastMonthlyRecord.age_reported !== null && lastMonthlyRecord.age_reported !== undefined">
                        {{ lastMonthlyRecord.age_reported }} años
                      </span>
                      <span v-else>-</span>
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Residencia
                    </div>
                    <div class="fw-semibold">
                      {{ countryName(lastMonthlyRecord.residence_country) }}
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Repatriación
                    </div>
                    <div class="fw-semibold">
                      {{ countryName(lastMonthlyRecord.repatriation_country) }}
                    </div>
                  </div>
                </div>

                <div class="row mb-3">
                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Mes de cobertura
                    </div>
                    <div class="fw-semibold">
                      {{ formatMonth(lastMonthlyRecord.coverage_month) }}
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Versión de plan
                    </div>
                    <div class="fw-semibold">
                      {{ lastMonthlyRecord.plan_version_id ?? '-' }}
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Lote de carga
                    </div>
                    <div class="fw-semibold">
                      {{ lastMonthlyRecord.load_batch_id ?? '-' }}
                    </div>
                  </div>
                </div>

                <div class="row mb-3">
                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Precio base
                    </div>
                    <div class="fw-semibold">
                      {{ formatMoney(lastMonthlyRecord.price_base) }}
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Recargo por edad
                    </div>
                    <div class="fw-semibold">
                      <span
                        v-if="
                          lastMonthlyRecord.age_surcharge_percent !== null &&
                          lastMonthlyRecord.age_surcharge_amount !== null
                        "
                      >
                        {{ lastMonthlyRecord.age_surcharge_percent }}%
                        ({{ formatMoney(lastMonthlyRecord.age_surcharge_amount) }})
                      </span>
                      <span v-else>
                        0%
                      </span>
                    </div>
                    <div
                      v-if="lastMonthlyRecord.age_surcharge_rule_id"
                      class="small"
                    >
                      Regla: #{{ lastMonthlyRecord.age_surcharge_rule_id }}
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Precio final
                    </div>
                    <div class="fw-semibold">
                      {{ formatMoney(lastMonthlyRecord.price_final) }}
                    </div>
                  </div>
                </div>

                <div class="row mb-3">
                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Origen del precio
                    </div>
                    <div class="fw-semibold">
                      <span v-if="lastMonthlyRecord.price_source === 'country'">
                        Tarifa por país
                      </span>
                      <span v-else-if="lastMonthlyRecord.price_source === 'global'">
                        Precio base
                      </span>
                      <span v-else>
                        {{ lastMonthlyRecord.price_source || '-' }}
                      </span>
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Fecha de alta (registro)
                    </div>
                    <div class="fw-semibold">
                      <span v-if="lastMonthlyRecord.created_at">
                        {{ formatDateTime(lastMonthlyRecord.created_at) }}
                      </span>
                      <span v-else>-</span>
                    </div>
                  </div>

                  <div class="col-md-4 mb-2">
                    <div class="text-muted">
                      Última actualización (registro)
                    </div>
                    <div class="fw-semibold">
                      <span v-if="lastMonthlyRecord.updated_at">
                        {{ formatDateTime(lastMonthlyRecord.updated_at) }}
                      </span>
                      <span v-else>-</span>
                    </div>
                  </div>
                </div>

                <div class="text-muted mt-2">
                  Se muestra el último mes cargado para este contrato.
                </div>
              </div>
            </section>
          </div>
        </div>

        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-light"
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
import axios from 'axios';
import {
  formatMonth as fmtMonth,
  formatDate as fmtDate,
  formatDatetime as fmtDatetime,
} from '../../../utils/format';

export default {
  name: 'ContractDashboardModal',

  props: {
    companyId: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      loading: false,
      error: null,
      contract: null,
      lastMonthlyRecord: null,
      modalInstance: null,
      currentContractId: null,
    };
  },

  mounted() {
    // Modal NO bloqueante
    if (window.bootstrap && window.bootstrap.Modal && this.$refs.modalEl) {
      this.modalInstance = new window.bootstrap.Modal(this.$refs.modalEl);
    }
  },

  methods: {
    route(name, params = {}) {
      return window.route ? window.route(name, params) : '#';
    },

    async open(contractId) {
      this.currentContractId = contractId;
      this.loading = true;
      this.error = null;
      this.contract = null;
      this.lastMonthlyRecord = null;

      if (this.modalInstance) {
        this.modalInstance.show();
      } else if (this.$refs.modalEl) {
        this.$refs.modalEl.style.display = 'block';
        this.$refs.modalEl.classList.add('show');
      }

      try {
        const url = this.route('admin.companies.capitated.contracts.show', {
          company: this.companyId,
          contract: contractId,
        });

        const { data } = await axios.get(url);

        this.contract = data.contract || null;
        this.lastMonthlyRecord = data.last_monthly_record || null;
      } catch (e) {
        this.error = 'No se pudo cargar la información del contrato.';
      } finally {
        this.loading = false;
      }
    },

    close() {
      if (this.modalInstance) {
        this.modalInstance.hide();
      } else if (this.$refs.modalEl) {
        this.$refs.modalEl.classList.remove('show');
        this.$refs.modalEl.style.display = 'none';
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

    sexLabel(sex) {
      if (!sex) return '';
      const s = String(sex).toUpperCase();
      if (s === 'M') return 'Masculino';
      if (s === 'F') return 'Femenino';
      return s;
    },

    translate(value) {
      if (this.$root && typeof this.$root.translate === 'function') {
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

    countryName(country) {
      if (!country) return '-';
      const name = country.name ?? '';
      const translated = this.translate(name);
      return translated || (typeof name === 'string' ? name : '-') || '-';
    },

    formatMonth(value) {
      if (!value) return '';
      return fmtMonth(value);
    },

    formatDate(value) {
      if (!value) return '';
      return fmtDate(value);
    },

    // Mantengo el nombre usado en el template
    formatDateTime(value) {
      if (!value) return '';
      return fmtDatetime(value);
    },

    formatMoney(value) {
      if (value === null || value === undefined) {
        return '-';
      }

      const n = Number(value);
      if (Number.isNaN(n)) {
        return String(value);
      }

      return n.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },
  },
};
</script>
