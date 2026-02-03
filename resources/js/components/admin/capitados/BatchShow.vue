<!--resources/js/components/admin/capitados/BatchShow.vue-->
<template>
  <div class="card">
    <div class="card-header border-0 pt-5 pb-3 d-flex justify-content-between align-items-center">
      <div>
        <h3 class="card-title fw-bold mb-1">
          Lote de carga&nbsp;<span v-if="batch?.id">#{{ batch.id }} - {{ formatMonth(batch.coverage_month) }}</span>
        </h3>
      </div>

      <div class="text-end">
        <div v-if="headerError" class="text-danger small mb-2">
          {{ headerError }}
        </div>

        <button
          type="button"
          class="btn btn-sm btn-danger"
          v-if="batch && batch.can_rollback"
		  :disabled="rollingBackBatch"
          @click="confirmBatchRollback"
        >
          <span v-if="rollingBackBatch" class="spinner-border spinner-border-sm me-2"></span>
          Rollback lote
        </button>
      </div>
    </div>

    <div class="card-body pt-0">
      <div v-if="headerLoading" class="mb-4 text-muted small">
        Cargando encabezado del batch…
      </div>

      <!-- Detalle del lote -->
      <div v-if="batch" class="">
        <div class="row g-3 small mb-5">
          <div class="col-12 col-md-4 col-lg-3">
            <div class="border rounded p-2 h-100">
              <div class="fw-semibold">Estado</div>
              <div>
                <span class="badge" :class="statusBadgeClass(batch.status)">
                  {{ statusLabel(batch.status) }}
                </span>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-4 col-lg-3">
            <div class="border rounded p-2 h-100">
              <div class="fw-semibold">Archivo fuente</div>
              <div class="fw-bold">
                <a
                  v-if="batch.file_temporary_url"
                  :href="batch.file_temporary_url"
                  target="_blank"
                  rel="noopener"
                >
                  {{ batch.original_filename || '(Sin nombre de archivo)' }}
                </a>
                <span v-else>
                  {{ batch.original_filename || '(Sin archivo)' }}
                </span>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-4 col-lg-3">
            <div class="border rounded p-2 h-100">
              <div class="fw-semibold">Creado por</div>
              <div class="fw-bold">
                <span v-if="batch.created_by && batch.created_by.display_name">
                  {{ batch.created_by.display_name }}
                </span>
                <span v-else>
                  {{ batch.created_by_user_id ? ('ID ' + batch.created_by_user_id) : '—' }}
                </span>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-4 col-lg-3">
            <div class="border rounded p-2 h-100">
              <div class="fw-semibold">Procesado en</div>
              <div class="fw-bold">
                {{ batch.processed_at ? formatDateTime(batch.processed_at) : '—' }}
              </div>
            </div>
          </div>
          <!--
          <div class="col-12 col-md-4 col-lg-3">
            <div class="border rounded p-2 h-100">
              <div class="fw-semibold">Permite cualquier mes</div>
              <div class="fw-bold">
                {{ batch.is_any_month_allowed ? 'Sí' : 'No' }}
              </div>
            </div>
          </div>
          -->

          <div v-if="batch.rolled_back_at" class="col-12 col-md-4 col-lg-3">
            <div class="border rounded p-2 h-100">
              <div class="fw-semibold">Revertido en</div>
              <div class="fw-bold">
                {{ batch.rolled_back_at ? formatDateTime(batch.rolled_back_at) : '—' }}
              </div>
            </div>
          </div>
          <div v-if="batch.rolled_back_by_user_id" class="col-12 col-md-4 col-lg-3">
            <div class="border rounded p-2 h-100">
              <div class="fw-semibold">Usuario rollback (ID)</div>
              <div class="fw-bold">
                {{ batch.rolled_back_by_user_id || '—' }}
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 small mb-5">
          <div class="col-12">
            <div class="p-2 h-100">
              <div class="fw-semibold">Resumen</div>
              <div v-if="summaryErrors.length === 0" class="small">
                Sin información de errores.
              </div>
              <div v-else class="">
                <table class="table table-sm table-bordered table-condensed table-striped mb-0">
                  <thead>
                    <tr>
                      <th class="small">Descripción</th>
                      <th class="small text-end">Cantidad</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(err, idx) in summaryErrors" :key="idx">
                      <td class="small">{{ err.description }}</td>
                      <td class="small text-end">
                        {{ err.count != null ? err.count : '—' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Resumen numérico -->
      <div v-if="batch" class="mb-5">
        <div class="row g-3 small">
          <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
              <div class="text-muted">Total filas</div>
              <div class="fw-bold fs-5">{{ batch.total_rows ?? 0 }}</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
              <div class="text-muted">Aprobadas</div>
              <div class="fw-bold fs-5 text-success">
                {{ batch.total_applied ?? 0 }}
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
              <div class="text-muted">Rechazadas</div>
              <div class="fw-bold fs-5 text-danger">
                {{ batch.total_rejected ?? 0 }}
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
              <div class="text-muted">Duplicadas</div>
              <div class="fw-bold fs-5 text-warning">
                {{ batch.total_duplicated ?? 0 }}
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3 small mt-1">
          <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
              <div class="text-muted">Incongruencias</div>
              <div class="fw-bold fs-5">
                {{ batch.total_incongruences ?? 0 }}
              </div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
              <div class="text-muted">Errores de plan</div>
              <div class="fw-bold fs-5">
                {{ batch.total_plan_errors ?? 0 }}
              </div>
            </div>
          </div>
          <div v-if="batch.total_rolled_back > 0" class="col-6 col-md-3">
            <div class="border rounded p-2 h-100">
              <div class="text-muted">Revertidas</div>
              <div class="fw-bold fs-5">
                {{ batch.total_rolled_back ?? 0 }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="items.length !== 0 || monthlyRecords.length !== 0">
        <!-- Tabs simples: Items / Cargas mensuales -->
        <ul class="nav nav-tabs nav-line-tabs mb-5">
          <li class="nav-item">
            <button
              class="nav-link"
              :class="{ active: activeTab === 'monthly' }"
              type="button"
              @click="activeTab = 'monthly'"
            >
              Cargas aceptadas
            </button>
          </li>
          <li class="nav-item">
            <button
              class="nav-link"
              :class="{ active: activeTab === 'items' }"
              type="button"
              @click="activeTab = 'items'"
            >
              Depuración
            </button>
          </li>
        </ul>

        <!-- TAB: ITEMS -->
        <div v-if="activeTab === 'items'">
          <div class="d-flex justify-content-between align-items-center mb-3 small">
            <div class="d-flex align-items-center">
              <div>
                <select
                  v-model="itemsSheetFilter"
                  class="form-select form-select-sm d-inline-block"
                  style="width: auto;"
                  @change="fetchItems(1)"
                >
                  <option
                    v-for="sheet in itemsSheets"
                    :key="sheet"
                    :value="sheet"
                  >
                    {{ sheet }}
                  </option>
                </select>
              </div>
              <div class="ms-2">
                <select
                  v-model="itemsResultFilter"
                  class="form-select form-select-sm d-inline-block"
                  style="width: auto;"
                  @change="fetchItems(1)"
                >
                  <option value="">Todos</option>
                  <option value="applied">Aplicado</option>
                  <option value="rejected">Rechazado</option>
                  <option value="duplicated">Duplicado</option>
                  <option value="incongruence">Incongruencia</option>
                </select>
              </div>
            </div>

            <div class="text-muted">
              <span v-if="itemsLoading">Cargando items…</span>
            </div>
          </div>

          <div v-if="itemsError" class="alert alert-danger py-2 small">
            {{ itemsError }}
          </div>

          <div v-else>
            <div v-if="items.length === 0" class="text-muted small">
              No hay items registrados para este batch.
            </div>

            <div v-else class="table-responsive">
              <table class="table table-row-dashed table-sm table-condensed table-hover table-striped align-middle mb-3">
                <thead>
                  <tr>
                    <th class="text-muted small text-end">Fila</th>
                    <th class="text-muted small">Documento</th>
                    <th class="text-muted small">Nombre</th>
                    <th class="text-muted small">Repatriación</th>
                    <th class="text-muted small">Residencia</th>
                    <th class="text-muted small">Edad</th>
                    <th class="text-muted small">Resultado</th>
                    <th class="text-muted small">Detalle</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="it in items" :key="it.id">
                    <td class="small text-muted text-end">
                      {{ it.row_number }}
                    </td>
                    <td class="small">
                      {{ it.document_number }}
                    </td>
                    <td class="small">
                      {{ it.full_name }}
                    </td>


                    <!-- Repatriación: raw + nombre de país detectado (si existe FK) -->
                    <td class="small text-muted">
                      <div>{{ it.repatriation_raw }}</div>
                      <div v-if="it.repatriation_country && it.repatriation_country.name">
                        <b>&rarr;{{ translate(it.repatriation_country.name) }}</b>
                      </div>
                    </td>

                    <!-- Residencia: raw + nombre de país detectado (si existe FK) -->
                    <td class="small text-muted">
                      <div>{{ it.residence_raw }}</div>
                      <div v-if="it.residence_country && it.residence_country.name">
                        <b>&rarr;{{ translate(it.residence_country.name) }}</b>
                      </div>
                    </td>
                    <td class="small text-muted">
                      {{ it.age_reported }}
                    </td>

                    <td class="small">
                      <span class="badge" :class="itemResultBadgeClass(it.result)">
                        {{ itemResultLabel(it.result) }}
                      </span>
                    </td>
                    <td class="small text-muted">
                      {{ it.rejection_detail || '' }}
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="d-flex justify-content-between align-items-center small text-muted">
                <span>Total: {{ itemsMeta.total }}</span>
                <div>
                  <button
                    type="button"
                    class="btn btn-xs btn-light me-2"
                    :disabled="itemsMeta.current_page <= 1 || itemsLoading"
                    @click="fetchItems(itemsMeta.current_page - 1)"
                  >
                    ‹
                  </button>
                  <button
                    type="button"
                    class="btn btn-xs btn-light"
                    :disabled="itemsMeta.current_page >= itemsMeta.last_page || itemsLoading"
                    @click="fetchItems(itemsMeta.current_page + 1)"
                  >
                    ›
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TAB: CARGAS MENSUALES -->
        <div v-if="activeTab === 'monthly'">
          <div class="d-flex justify-content-between align-items-center mb-3 small">
            <div class="d-flex align-items-center">
              <div class="me-2">
                <select
                  v-model="monthlyProductFilter"
                  class="form-select form-select-sm d-inline-block"
                  style="width: auto;"
                  @change="fetchMonthlyRecords(1)"
                >
                  <option value="">Todos los productos</option>
                  <option
                    v-for="prod in monthlyProducts"
                    :key="prod.id"
                    :value="prod.id"
                  >
                    ({{ prod.id }}) {{ prod.name }}
                  </option>
                </select>
              </div>

              <div>
                <select
                  v-model="monthlyStatusFilter"
                  class="form-select form-select-sm d-inline-block"
                  style="width: auto;"
                  @change="fetchMonthlyRecords(1)"
                >
                  <option value="">Todos los estados</option>
                  <option value="active">Activo</option>
                  <option value="rolled_back">Revertido</option>
                </select>
              </div>
            </div>

            <div class="text-muted">
              <span v-if="monthlyLoading">Cargando cargas mensuales…</span>
            </div>
          </div>

          <div v-if="monthlyError" class="alert alert-danger py-2 small">
            {{ monthlyError }}
          </div>

          <div v-else>
            <div v-if="monthlyRecords.length === 0" class="text-muted small">
              No hay cargas mensuales asociadas a este batch.
            </div>

            <div v-else class="table-responsive">
              <table class="table table-row-dashed table-sm table-condensed table-hover table-striped align-middle mb-3">
                <thead>
                  <tr>
                    <th class="text-muted small">Contrato</th>
                    <th class="text-muted small">Documento</th>
                    <th class="text-muted small">Persona</th>
                    <th class="text-muted small">Repatriación</th>
                    <th class="text-muted small">Residencia</th>
                    <th class="text-muted small text-end">Base</th>
                    <th class="text-muted small">Edad</th>
                    <th class="text-muted small text-end">% Edad</th>
                    <th class="text-muted small text-end">Total</th>
                    <th class="text-muted small">Estado</th>
                    <th class="text-end"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="mr in monthlyRecords" :key="mr.id">
                    <td class="small text-muted">
                      #{{ mr.contract_id }}
                    </td>
                    <td class="small">
                      {{ mr.person?.document_number || '' }}
                    </td>
                    <td class="small">
                      #{{ mr.person.id }} -
                      <span
                        :style="{
                          'text-decoration':
                            mr.person.status === 'rolled_back' ? 'line-through' : 'none',
                        }"
                      >
                        {{ (mr.person.full_name || '(Sin nombre)') }}
                      </span>
                    </td>
                    <td class="small text-muted">
                      {{ translate(mr.repatriation_country?.name) || '' }}
                    </td>
                    <td class="small text-muted" :class="{'text-base-country':mr.price_source=='country'}">
                      {{ translate(mr.residence_country?.name) || '' }}
                    </td>
                    <td class="small text-end" :class="{'text-base-country':mr.price_source=='country'}">
                      {{ formatMoney(mr.price_base) }}
                    </td>
                    <td class="small text-end" :class="{'text-age':mr.age_surcharge_rule_id!=null}">
                      {{ mr.age_reported }}
                    </td>
                    <td class="small text-end"  :class="{'text-age':mr.age_surcharge_rule_id!=null}">
                      {{ (mr.age_surcharge_percent==''||mr.age_surcharge_percent==0||mr.age_surcharge_percent==null) ? '' : '+'+formatMoney(mr.age_surcharge_percent)+'%' }}
                    </td>
                    <td class="small text-end">
                      {{ formatMoney(mr.price_final) }}
                    </td>
                    <td class="small">
                      <span class="badge" :class="monthlyStatusBadgeClass(mr.status)">
                        {{ monthlyStatusLabel(mr.status) }}
                      </span>
                    </td>
                    <td class="text-end">
                      <button
                        type="button"
                        class="btn btn-sm btn-light-danger"
                        v-if="mr.can_rollback && !(rollingBackRecordId === mr.id)"
                        @click="confirmRecordRollback(mr)"
                      >
                        <span
                          v-if="rollingBackRecordId === mr.id"
                          class="spinner-border spinner-border-sm me-1"
                        ></span>
                        Rollback
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>

              <div class="d-flex justify-content-between align-items-center small text-muted">
                <span>Total: {{ monthlyMeta.total }}</span>
                <div>
                  <button
                    type="button"
                    class="btn btn-xs btn-light me-2"
                    :disabled="monthlyMeta.current_page <= 1 || monthlyLoading"
                    @click="fetchMonthlyRecords(monthlyMeta.current_page - 1)"
                  >
                    ‹
                  </button>
                  <button
                    type="button"
                    class="btn btn-xs btn-light"
                    :disabled="monthlyMeta.current_page >= monthlyMeta.last_page || monthlyLoading"
                    @click="fetchMonthlyRecords(monthlyMeta.current_page + 1)"
                  >
                    ›
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
import axios from 'axios';
import {
  formatMonth as fmtMonth,
  formatDatetime as fmtDatetime,
} from '../../../utils/format';

export default {
  name: 'CapitatedBatchShow',

  props: {
    companyId: {
      type: Number,
      required: true,
    },
    batchId: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      activeTab: 'monthly',

      // encabezado
      batch: null,
      headerLoading: false,
      headerError: null,

      // items
      items: [],
      itemsMeta: {
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 25,
      },
      itemsLoading: false,
      itemsError: null,
      itemsResultFilter: '',
      itemsSheets: [],
      itemsSheetFilter: '',

      // cargas mensuales
      monthlyRecords: [],
      monthlyMeta: {
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 25,
      },
      monthlyLoading: false,
      monthlyError: null,
      monthlyStatusFilter: '',
      monthlyProducts: [],
      monthlyProductFilter: '',

      rollingBackBatch: false,
      rollingBackRecordId: null,
    };
  },

  computed: {
    parsedSummaryJson() {
      if (!this.batch || !this.batch.summary_json) {
        return null;
      }

      if (typeof this.batch.summary_json === 'object') {
        return this.batch.summary_json;
      }

      try {
        return JSON.parse(this.batch.summary_json);
      } catch (e) {
        return null;
      }
    },

    summaryErrors() {
      const data = this.parsedSummaryJson;
      if (!data || typeof data !== 'object') {
        return [];
      }

      const descriptions = {
        PERSON_COUNTRY_CODE_NOT_FOUND: 'País no encontrado en catálogo.',
        PERSON_REPATRIATION_NOT_ALLOWED: 'País de repatriación no permitido para el plan.',
        PERSON_DUPLICATED: 'Persona duplicada para este producto y mes.',
        PERSON_RESIDENCE_NOT_ALLOWED: 'País de residencia no permitido para el plan.',
        PERSON_SEX_INVALID: 'Sexo inválido o no permitido.',
        PERSON_AGE_INVALID: 'Edad inválida o fuera de rango.',
        PERSON_INCONGRUENCE: 'Incongruencia con ficha existente de la persona.',
        RETROACTIVE_NOT_ALLOWED: 'Carga retroactiva no permitida.',
        PLAN_INVALID_PRODUCT: 'Producto de plan inválido para la empresa.',
        PLAN_NO_ACTIVE_VERSION: 'Producto sin versión activa.',
        PLAN_STRUCTURE_INVALID: 'Estructura de hoja Excel inválida.',
        UNKNOWN_ERROR: 'Error desconocido.',
      };

      const rows = [];

      // 1) Aggregate errors_by_code (usamos solo descripción y cantidad)
      if (data.errors_by_code && typeof data.errors_by_code === 'object') {
        Object.keys(data.errors_by_code).forEach((code) => {
          const count = data.errors_by_code[code];
          const baseDescription =
            descriptions[code] || (code ? String(code) : 'Sin descripción disponible.');
          rows.push({
            description: baseDescription,
            count: typeof count === 'number' ? count : null,
          });
        });
      }

      // 2) Plan errors detallados → "sheet: message"
      if (Array.isArray(data.plan_errors)) {
        data.plan_errors.forEach((err, index) => {
          if (!err || typeof err !== 'object') {
            rows.push({
              description: `[ plan_errors ][ ${index} ] [ ${this.stringifyJsonValue(err)} ]`,
              count: null,
            });
            return;
          }

          const code = err.code || null;
          const sheet = err.sheet ? String(err.sheet) : '';
          const messageFromError = err.message ? String(err.message) : '';
          const fallbackFromCode = code && descriptions[code] ? descriptions[code] : '';
          const mainMessage =
            messageFromError || fallbackFromCode || 'Error de plan.';

          let text;
          if (sheet && mainMessage) {
            text = `${sheet}: ${mainMessage}`;
          } else if (sheet) {
            text = sheet;
          } else {
            text = mainMessage;
          }

          rows.push({
            description: text,
            count: null,
          });
        });
      }

      // 3) Claves raíz adicionales que no conocemos → "[ KEY ] [ value ]"
      const knownRoot = ['errors_by_code', 'plan_errors', 'error_summary'];
      Object.keys(data).forEach((key) => {
        if (!knownRoot.includes(key)) {
          const valueText = this.stringifyJsonValue(data[key]);
          const desc =
            valueText !== ''
              ? `[ ${key} ] [ ${valueText} ]`
              : `[ ${key} ]`;
          rows.push({
            description: desc,
            count: null,
          });
        }
      });

      return rows;
    },
  },

  mounted() {
    this.loadHeader();
    this.fetchItems(1);
    this.fetchMonthlyRecords(1);
  },

  methods: {
    stringifyJsonValue(value) {
      if (value === null || value === undefined) return '';
      if (
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
      ) {
        return String(value);
      }
      try {
        return JSON.stringify(value);
      } catch (e) {
        return String(value);
      }
    },

    route(name, params = {}) {
      return window.route ? window.route(name, params) : '#';
    },

    // --------------------------
    // Encabezado
    // --------------------------
    async loadHeader() {
      this.headerLoading = true;
      this.headerError = null;

      try {
        const url = this.route('admin.companies.capitated.batches.show', {
          company: this.companyId,
          batch: this.batchId,
        });

        const { data } = await axios.get(url);
        this.batch = data.batch || null;
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
        this.headerError = 'No se pudo cargar el encabezado del batch.';
      } finally {
        this.headerLoading = false;
      }
    },

    // --------------------------
    // Items
    // --------------------------
    async fetchItems(page = 1) {
      if (!this.batchId) return;

      this.itemsLoading = true;
      this.itemsError = null;

      try {
        const url = this.route('admin.companies.capitated.batches.items', {
          company: this.companyId,
          batch: this.batchId,
          page,
          per_page: this.itemsMeta.per_page || 25,
          result: this.itemsResultFilter || undefined,
          sheet: this.itemsSheetFilter || undefined,
        });

        const { data } = await axios.get(url);

        this.items = data.data || [];
        this.itemsMeta = data.meta || this.itemsMeta;

        this.itemsSheets = Array.isArray(data.sheets) ? data.sheets : [];

        if (data.filters && data.filters.sheet) {
          this.itemsSheetFilter = data.filters.sheet;
        } else if (!this.itemsSheetFilter && this.itemsSheets.length > 0) {
          this.itemsSheetFilter = this.itemsSheets[0];
        }
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
        this.itemsError = 'No se pudieron cargar los items del batch.';
      } finally {
        this.itemsLoading = false;
      }
    },

    itemResultBadgeClass(result) {
      if (result === 'applied') return 'badge-light-success';
      if (result === 'rejected') return 'badge-light-danger';
      if (result === 'duplicated') return 'badge-light-warning';
      if (result === 'incongruence') return 'badge-light-info';
      return 'badge-light-secondary';
    },

    itemResultLabel(result) {
      if (result === 'applied') return 'Aplicado';
      if (result === 'rejected') return 'Rechazado';
      if (result === 'duplicated') return 'Duplicado';
      if (result === 'incongruence') return 'Incongruencia';
      return result || '';
    },

    // --------------------------
    // Cargas mensuales
    // --------------------------
    async fetchMonthlyRecords(page = 1) {
      if (!this.batchId) return;

      this.monthlyLoading = true;
      this.monthlyError = null;

      try {
        const url = this.route('admin.companies.capitated.batches.monthly_records.index', {
          company: this.companyId,
          batch: this.batchId,
          page,
          per_page: this.monthlyMeta.per_page || 25,
          status: this.monthlyStatusFilter || undefined,
          product_id: this.monthlyProductFilter || undefined,
        });

        const { data } = await axios.get(url);

        this.monthlyRecords = data.data || [];
        this.monthlyMeta = data.meta || this.monthlyMeta;

        this.monthlyProducts = Array.isArray(data.products) ? data.products : [];

        if (
          data.filters &&
          typeof data.filters.product_id !== 'undefined' &&
          data.filters.product_id !== null &&
          data.filters.product_id !== ''
        ) {
          this.monthlyProductFilter = data.filters.product_id;
        } else if (!this.monthlyProductFilter) {
          this.monthlyProductFilter = '';
        }
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
        this.monthlyError = 'No se pudieron cargar las cargas mensuales del batch.';
      } finally {
        this.monthlyLoading = false;
      }
    },

    monthlyStatusBadgeClass(status) {
      if (status === 'active' || status === null) return 'badge-light-success';
      if (status === 'rolled_back') return 'badge-light-secondary';
      return 'badge-light-secondary';
    },

    monthlyStatusLabel(status) {
      if (status === 'active' || status === null) return 'Vigente';
      if (status === 'rolled_back') return 'Revertido';
      return status || '';
    },

    // --------------------------
    // Rollback
    // --------------------------
    async confirmBatchRollback() {
      if (!this.batch || !this.batch.can_rollback) return;

      const ok = window.confirm(
        '¿Seguro que deseas hacer rollback de todo el lote?\n' +
          'Se intentará revertir todas las cargas mensuales generadas por este batch; ' +
          'solo se revertirán las que sean elegibles (último mes vigente de cada contrato).',
      );
      if (!ok) return;

      this.rollingBackBatch = true;

      try {
        const url = this.route('admin.companies.capitated.batches.rollback', {
          company: this.companyId,
          batch: this.batchId,
        });

        const { data } = await axios.post(url);

        this.batch = data.batch || this.batch;

        // Refrescar listas
        this.fetchMonthlyRecords(this.monthlyMeta.current_page || 1);
        this.fetchItems(this.itemsMeta.current_page || 1);

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash('Lote revertido correctamente.', 'success');
        }
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
        const msg = e?.response?.data?.message || 'No se pudo revertir el lote.';
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(msg, 'danger');
        }
      } finally {
        this.rollingBackBatch = false;
      }
    },

    async confirmRecordRollback(record) {
      if (!record || !record.id || !record.can_rollback) return;

      const ok = window.confirm(
        '¿Seguro que deseas hacer rollback de esta carga mensual?',
      );
      if (!ok) return;

      this.rollingBackRecordId = record.id;

      try {
        const url = this.route('admin.companies.capitated.batches.monthly_records.rollback', {
          company: this.companyId,
          batch: this.batchId,
          record: record.id,
        });

        await axios.post(url);

        // Refrescar listas
        this.fetchMonthlyRecords(this.monthlyMeta.current_page || 1);
        this.loadHeader();

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash('Registro mensual revertido correctamente.', 'success');
        }
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
        const msg =
          e?.response?.data?.message || 'No se pudo revertir el registro mensual.';
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(msg, 'danger');
        }
      } finally {
        this.rollingBackRecordId = null;
      }
    },

    // --------------------------
    // Helpers de formato
    // --------------------------
    formatMonth(value) {
      if (!value) return '';
      return fmtMonth(value);
    },

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

    statusBadgeClass(status) {
      if (status === 'processed') return 'badge-light-success';
      if (status === 'failed') return 'badge-light-danger';
      if (status === 'draft') return 'badge-light-secondary';
      if (status === 'rolled_back') return 'badge-light-secondary';
      return 'badge-light-secondary';
    },

    statusLabel(status) {
      if (status === 'processed') return 'Procesado';
      if (status === 'failed') return 'Fallido';
      if (status === 'draft') return 'Borrador';
      if (status === 'rolled_back') return 'Revertido';
      return status || '';
    },
  },
};
</script>
<style scoped>
	.text-base-country
	{
		font-weight: bold!important;
		background-color: lightgoldenrodyellow;
	}
	.text-age
	{
		font-weight: bold!important;
		background-color: lightcyan;
	}
</style>