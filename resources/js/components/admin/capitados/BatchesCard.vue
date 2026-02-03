<!-- resources/js/components/admin/capitados/BatchesCard.vue -->
<template>
  <div class="card">
    <div class="card-header border-0 pt-6 pb-0 d-flex justify-content-between align-items-center">
      <div class="card-title">
        <h3 class="card-label fw-bold mb-0">
          Batches de carga
        </h3>

        <a
          :href="templateUrl"
          class="btn btn-light-primary btn-sm ms-3"
        >
          Descargar Plantilla
        </a>
      </div>

      <!-- Toolbar: selector de mes (según permisos) + botones de archivo -->
      <div class="card-toolbar d-flex flex-column flex-md-row align-items-stretch align-items-md-center gap-2">
        <!-- Selector de mes: solo si tiene capitados.batch.create_any_month -->

        <div class="input-group input-group-sm">
          <VueDatePicker
			v-if="canSelectAnyMonth"
            v-model="selectedMonth"
            month-picker
            auto-apply
            :enable-time-picker="false"
            :formats="monthPickerFormats"
            :clearable="false"
            :month-change-on-scroll="false"
            :year-change-on-scroll="false"
			class="form-control month"
          />

          <!-- 1) Nombre de archivo: solo cuando hay archivo seleccionado -->
          <label
            id="nombre-archivo"
            class="form-control text-muted"
            v-if="!!uploadFile"
          >
            {{ uploadFile?.name }}
          </label>

          <!-- 3) Botón seleccionar: solo cuando NO hay archivo (se esconde cuando hay uno) -->
          <label
            class="btn btn-sm btn-success mb-0"
            v-if="!uploadFile"
          >
            <input
              ref="fileInput"
              type="file"
              class="d-none"
              accept=".xlsx,.xls"
              @change="onFileChange"
            />
            <i class="bi bi-file-earmark-excel"></i> Seleccionar Archivo
          </label>

          <!-- 2) Botón quitar: solo cuando hay archivo -->
          <button
            id="clear-archivo"
            type="button"
            class="btn btn-sm btn-danger"
            v-if="!!uploadFile"
            :disabled="uploading"
            @click="clearSelectedFile"
          >
            <i class="bi bi-x-lg"></i> Quitar
          </button>

          <!-- 3) Botón subir: solo cuando hay archivo -->
          <button
            type="button"
            class="btn btn-sm btn-light"
            v-if="uploadFile"
            :class="{'btn-success': (uploadFile && !uploading)}"
            :disabled="!uploadFile || uploading"
            @click="uploadExcel"
          >
            <span v-if="uploading" class="spinner-border spinner-border-sm me-2"></span>
            <i class="bi bi-cloud-upload"></i> Subir
          </button>
        </div>
      </div>
    </div>

    <div class="card-body pt-4">
      <div v-if="uploadError" class="alert alert-danger py-2 small mb-3">
        {{ uploadError }}
      </div>

      <div v-if="error" class="alert alert-danger py-2 small mb-3">
        {{ error }}
      </div>

      <div class="position-relative">
        <!-- Overlay flotante de carga, sin mover el layout -->
        <div
          v-if="loading && hasData"
          class="position-absolute top-0 end-0 mt-2 me-3 small text-muted bg-white bg-opacity-75 rounded px-2 py-1 shadow-sm"
        >
          Cargando...
        </div>

        <div v-if="!hasData && !loading" class="text-muted small">
          No hay batches registrados todavía.
        </div>

        <div v-else-if="hasData" class="table-responsive">
          <table class="table table-row-dashed table-sm table-condensed table-hover table-striped align-middle mb-3">
            <thead>
              <tr>
                <th style="width: 4rem;">ID</th>
                <th>Mes</th>
                <th>Fecha y hora de carga</th>
                <th>Estado</th>
                <th class="text-end">Aprobadas</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in batches" :key="b.id">
                <td class="text-muted">#{{ b.id }}</td>
                <td class="text-muted small">
                  {{ formatCoverageMonth(b.coverage_month) }}
                </td>
                <td class="text-muted small">
                  {{ formatDateTime(b.processed_at || b.created_at) }}
                </td>
                <td>
                  <span class="badge" :class="statusBadgeClass(b.status)">
                    {{ statusLabel(b.status) }}
                  </span>
                </td>
                <td class="text-end text-muted small">
                  <span v-if="b.status === 'processed'">
                    {{ (b.total_applied ?? 0) }} / {{ (b.total_rows ?? 0) }}
                  </span>
                  <span v-else>—</span>
                </td>
                <td class="text-end">
                  <!-- Link a la nueva sección de detalle del batch -->
                  <a
                    :href="batchDetailUrl(b)"
                    class="btn btn-sm btn-light-primary me-1"
                    title="Ver detalle del batch"
                  >
                    <i class="bi bi-search"></i>
                  </a>

                  <a
                    v-if="b.file_temporary_url"
                    :href="b.file_temporary_url"
                    target="_blank"
                    class="btn btn-sm btn-secondary"
                    title="Ver Archivo"
                  >
                    <i class="bi bi-file-earmark-excel"></i>
                  </a>
                </td>
              </tr>
            </tbody>
          </table>

          <div class="d-flex justify-content-between align-items-center small text-muted">
            <span>Total: {{ meta.total }}</span>
            <div>
              <button
                type="button"
                class="btn btn-xs btn-light me-2"
                :disabled="loading || meta.current_page <= 1"
                @click="fetchPage(meta.current_page - 1)"
              >
                ‹
              </button>
              <button
                type="button"
                class="btn btn-xs btn-light"
                :disabled="loading || meta.current_page >= meta.last_page"
                @click="fetchPage(meta.current_page + 1)"
              >
                ›
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import axios from 'axios';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { formatMonth as fmtMonth, formatDatetime as fmtDatetime } from '../../../utils/format';

export default {
  name: 'CapitatedBatchesCard',

  components: {
    VueDatePicker,
  },

  props: {
    companyId: {
      type: Number,
      required: true,
    },
  },

  data() {
    const now = new Date();

    return {
      loading: false,
      error: null,
      batches: [],
      meta: {
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: null,
      },
      uploading: false,
      uploadError: null,
      uploadFile: null,
      perPage: this.perPageShort,

      // snapshot para detectar transiciones de estado por ID
      batchStatusById: {},

      // último batch subido desde este componente (para capturar el caso: ya viene "processed" al primer fetch)
      lastUploadedBatchId: null,

      // polling para detectar fin de procesamiento (sin depender de acciones del usuario)
      pollTimer: null,
      pollIntervalMs: 4000,
      polling: false,

      // mes seleccionado para la carga (month-picker: { month: 0-11, year: 2025 })
      selectedMonth: {
        month: now.getMonth(),
        year: now.getFullYear(),
      },
    };
  },

  computed: {
    hasData() {
      return this.batches && this.batches.length > 0;
    },

    templateUrl() {
      // endpoint existente, sin cambiar comportamiento
      return this.route('admin.companies.capitated.batches.template', { company: this.companyId });
    },

    // ¿El usuario puede elegir cualquier mes con el datepicker?
    canSelectAnyMonth() {
      return this.hasPermission('capitados.batch.create_any_month');
    },

    // Formato de salida para el prop `formats` del month-picker
    monthPickerFormats() {
      return {
        month: 'LLLL', // etiqueta de cada mes en el grid
      };
    },

    // Mes de cobertura que se envía al backend, siempre en YYYY-MM-01
    // - con permiso any_month: usa lo escogido en el picker
    // - sin permiso: mes actual
    coverageMonthIso() {
      let year;
      let monthIndex;

      if (
        this.canSelectAnyMonth &&
        this.selectedMonth &&
        this.selectedMonth.year !== undefined &&
        this.selectedMonth.month !== undefined
      ) {
        year = Number(this.selectedMonth.year);
        monthIndex = Number(this.selectedMonth.month);

        if (Number.isNaN(year) || Number.isNaN(monthIndex)) {
          const now = new Date();
          year = now.getFullYear();
          monthIndex = now.getMonth();
        }
      } else {
        const now = new Date();
        year = now.getFullYear();
        monthIndex = now.getMonth();
      }

      const d = new Date(year, monthIndex, 1);
      const yyyy = d.getFullYear();
      const mm = String(d.getMonth() + 1).padStart(2, '0');

      return `${yyyy}-${mm}-01`;
    },
  },

  mounted() {
    this.fetchPage(1);
  },

  beforeDestroy() {
    // Vue 2
    this.stopPolling();
  },

  beforeUnmount() {
    // Vue 3
    this.stopPolling();
  },

  methods: {
    route(name, params = {}) {
      return window.route ? window.route(name, params) : '#';
    },

    // Utilidad defensiva para permisos; ajusta internamente a tu implementación real
    hasPermission(ability) {
      try {
        if (this.$root) {
          if (typeof this.$root.can === 'function') {
            return !!this.$root.can(ability);
          }
          if (typeof this.$root.hasPermission === 'function') {
            return !!this.$root.hasPermission(ability);
          }
        }

        const globals = this.$?.appContext?.config?.globalProperties || null;
        if (globals) {
          if (typeof globals.can === 'function') {
            return !!globals.can(ability);
          }
          if (typeof globals.hasPermission === 'function') {
            return !!globals.hasPermission(ability);
          }
        }

        if (typeof window !== 'undefined') {
          if (typeof window.can === 'function') {
            return !!window.can(ability);
          }
          if (typeof window.hasPermission === 'function') {
            return !!window.hasPermission(ability);
          }
        }
      } catch (e) {
        // eslint-disable-next-line no-console
        console.warn('Error comprobando permisos de capitados', e);
      }

      return false;
    },

    batchDetailUrl(batch) {
      if (!batch || !batch.id) {
        return '#';
      }
      return this.route('admin.companies.capitated.batches.show', {
        company: this.companyId,
        batch: batch.id,
      });
    },

    async fetchPage(page = 1) {
      this.loading = true;
      this.error = null;

      try {
        const url = this.route('admin.companies.capitated.batches.index', {
          company: this.companyId,
          page,
          per_page: this.perPage,
        });

        const { data } = await axios.get(url);

        const newBatches = data.data || [];
        this.detectAndEmitAppliedBatch(newBatches);

        this.batches = newBatches;
        this.meta = Object.assign({}, this.meta, data.meta || {});

        // iniciar/detener polling según si aún hay drafts en la primera página
        this.syncPollingFromBatches(newBatches);
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
        this.error = 'No se pudieron cargar los batches.';
      } finally {
        this.loading = false;
      }
    },

    async pollLatestBatches() {
      if (this.polling) return;
      if (this.loading) return;

      this.polling = true;

      try {
        const url = this.route('admin.companies.capitated.batches.index', {
          company: this.companyId,
          page: 1,
          per_page: this.perPage,
        });

        const { data } = await axios.get(url);

        const newBatches = data.data || [];

        // detectar transición y emitir evento si corresponde
        this.detectAndEmitAppliedBatch(newBatches);

        // iniciar/detener polling según estado actual
        this.syncPollingFromBatches(newBatches);

        // si el usuario está viendo página 1, reflejamos cambios de estado en la tabla
        if ((this.meta?.current_page ?? 1) === 1) {
          this.batches = newBatches;
          this.meta = Object.assign({}, this.meta, data.meta || {});
        }
      } catch (e) {
        // silencioso: no romper UI por fallas puntuales de polling
        // eslint-disable-next-line no-console
        console.error(e);
      } finally {
        this.polling = false;
      }
    },

    startPolling() {
      if (this.pollTimer) return;

      this.pollTimer = setInterval(() => {
        this.pollLatestBatches();
      }, this.pollIntervalMs);
    },

    stopPolling() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
    },

    syncPollingFromBatches(batches) {
      const hasDraft = (batches || []).some((b) => b?.status === 'draft');

      if (hasDraft) {
        this.startPolling();
      } else {
        this.stopPolling();
      }
    },

    detectAndEmitAppliedBatch(batches) {
      for (const b of (batches || [])) {
        const id = b?.id;
        if (!id) continue;

        const prevStatus = this.batchStatusById[id];
        const currStatus = b?.status;

        // actualizar snapshot
        this.batchStatusById[id] = currStatus;

        const applied = Number(b?.total_applied ?? 0);

        // condición pedida: emitir SOLO si generó >= 1 registro nuevo aceptado/aplicado
        if (applied <= 0) continue;

        // caso normal: transición draft -> processed
        const transitionedToProcessed = (prevStatus === 'draft' && currStatus === 'processed');

        // caso borde: el batch ya viene processed al primer fetch post-upload
        const firstSeenProcessedForLastUpload = (!prevStatus && this.lastUploadedBatchId === id && currStatus === 'processed');

        if (transitionedToProcessed || firstSeenProcessedForLastUpload) {
          this.$emit('batch-applied', b);

          if (this.lastUploadedBatchId === id) {
            this.lastUploadedBatchId = null;
          }
        }
      }
    },

    onFileChange(e) {
      const files = e.target.files || [];
      this.uploadFile = files.length ? files[0] : null;
      this.uploadError = null;
    },

    clearSelectedFile() {
      this.uploadFile = null;
      this.uploadError = null;

      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = '';
      }
    },

    async uploadExcel() {
      if (!this.uploadFile) {
        this.uploadError = 'Selecciona un archivo Excel primero.';
        return;
      }

      this.uploading = true;
      this.uploadError = null;

      try {
        const url = this.route('admin.companies.capitated.batches.upload', {
          company: this.companyId,
        });

        const formData = new FormData();
        formData.append('file', this.uploadFile);

        // Siempre mandamos coverage_month:
        // - con permiso any_month: el mes escogido
        // - sin permiso: mes actual
        formData.append('coverage_month', this.coverageMonthIso);

        const { data } = await axios.post(url, formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        });

        // guardar ID del batch recién creado (si el backend lo retorna)
        const newBatchId = data?.batch?.id;
        if (newBatchId) {
          this.lastUploadedBatchId = newBatchId;
        }

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash('Archivo enviado. Revisa el estado en el listado de batches.', 'success');
        }

        this.clearSelectedFile();

        // cargar página 1 y dejar polling activo si quedó en draft
        this.fetchPage(1);
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e);
        const msg = e?.response?.data?.message || 'Error al subir el archivo.';
        this.uploadError = msg;
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(msg, 'danger');
        }
      } finally {
        this.uploading = false;
      }
    },

    statusBadgeClass(status) {
      if (status === 'processed') return 'badge-light-success';
      if (status === 'failed') return 'badge-light-danger';
      if (status === 'draft') return 'badge-light-secondary';
      return 'badge-light-secondary';
    },

    statusLabel(status) {
      if (status === 'processed') return 'Procesado';
      if (status === 'failed') return 'Fallido';
      if (status === 'draft') return 'Borrador';
      return status;
    },

    formatCoverageMonth(value) {
      if (!value) return '';
      return fmtMonth(value);
    },

    formatDateTime(value) {
      if (!value) return '';
      return fmtDatetime(value);
    },
  },
};
</script>
<style>
	.month.form-control
	{
		margin: 0px;
		padding: 0px;
	}
	
	.month.form-control input
	{
		border: none;
		margin: 0px;
	}
</style>