<template>
  <div v-if="canView" class="card mt-6">
    <div class="card-header">
      <h3 class="card-title fw-bold mb-0">Reportes Mensuales</h3>
    </div>

    <div class="card-body">
      <div v-if="loading" class="text-muted small">
        Cargando meses…
      </div>

      <div v-else-if="months.length === 0" class="text-muted small">
        No existen registros para generar reportes.
      </div>

      <div v-else class="table-responsive">
        <table class="table table-row-dashed table-sm table-condensed table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Mes</th>
              <th class="text-end">Cantidad de registros</th>
              <th class="text-end">Total</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="m in months" :key="m.month">
              <td class="text-muted small">
                {{ formatMonth(m.month) }}
              </td>
              <td class="text-end small">
                {{ formatInteger(m.active_count) }}
              </td>
              <td class="text-end small">
                {{ formatDecimal(m.active_total) }}
              </td>
              <td class="text-end">
                <a
                  :href="downloadUrl(m.month)"
                  class="btn btn-sm btn-light-primary"
                >
                  Descargar Excel
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios';
import {
  formatMonth,
  formatDecimal,
  formatInteger,
} from '../../../utils/format';

export default {
  name: 'CapitatedMonthlyReportsCard',

  props: {
    companyId: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      months: [],
      loading: false,
    };
  },

  computed: {
    canView() {
      return typeof window.can === 'function'
        ? window.can('capitados.reporte.mensual')
        : false;
    },
  },

  mounted() {
    if (this.canView) {
      this.fetchMonths();
    }
  },

  methods: {
    route(name, params = {}) {
      return window.route ? window.route(name, params) : '#';
    },

    formatMonth(value) {
      return formatMonth(value);
    },

    formatInteger(value) {
      return formatInteger(value);
    },

    formatDecimal(value) {
      return formatDecimal(value);
    },

    downloadUrl(month) {
      return this.route('admin.companies.capitated.reporte.mensual.download', {
        company: this.companyId,
        month,
      });
    },

    async fetchMonths() {
      this.loading = true;

      try {
        const { data } = await axios.get(
          this.route('admin.companies.capitated.reporte.mensual.months', {
            company: this.companyId,
          }),
        );

        this.months = data.months || [];
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
