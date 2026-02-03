<!-- resources/js/components/admin/regalias/Index.vue -->
<template>
  <div>
    <!-- Encabezado -->
    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h1 class="h3 mb-1">Regalías</h1>
        <div class="text-muted small">
          Configuración de regalías (por usuario y por unidad) para beneficiarios.
        </div>
      </div>

      <div class="d-flex gap-2">
        <button
          v-if="can('regalia.users.edit')"
          type="button"
          class="btn btn-primary"
          @click="openManageModal()"
        >
          Añadir regalía
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="row g-2 align-items-center">
          <div class="col-md-6">
            <input
              type="search"
              class="form-control"
              placeholder="Buscar beneficiario por nombre o correo…"
              v-model="filters.search"
              @input="onSearchInput"
            />
          </div>
          <div class="col-md-3">
            <select
              class="form-select"
              v-model="filters.per_page"
              @change="reloadFirstPage"
            >
              <option :value="perPageShort">Mostrar {{ perPageShort }}</option>
              <option :value="perPageMedium">Mostrar {{ perPageMedium }}</option>
              <option :value="perPageLarge">Mostrar {{ perPageLarge }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Lista de beneficiarios -->
    <div v-if="loading" class="text-muted">
      Cargando…
    </div>

    <div v-else>
      <div v-if="!beneficiaries.length" class="text-muted">
        No hay beneficiarios de regalías configurados.
      </div>

      <div v-else>
        <div
          class="card mb-4"
          v-for="card in beneficiaries"
          :key="card.beneficiary.id"
        >
          <div class="card-header d-flex align-items-center justify-content-between">
            <div>
              <div class="fw-bold">
                {{ card.beneficiary.display_name || 'error' }}
              </div>
              <div class="text-muted small">
                {{ card.beneficiary.email || 'error' }}
              </div>
              <span
                v-if="card.beneficiary.status && card.beneficiary.status !== 'active'"
                class="badge rounded-pill text-bg-danger mt-1"
              >
                {{ card.beneficiary.status }}
              </span>
            </div>

            <div
              v-if="can('regalia.users.edit')"
              class="d-flex flex-wrap gap-2"
            >
<!--              <button
                v-if="hasSourceType('user')"
                type="button"
                class="btn btn-sm btn-primary"
                @click="openManageModalForBeneficiary(card.beneficiary, 'user')"
              >
                Añadir regalía de usuario
              </button>-->
              <button
                v-if="hasSourceType('unit')"
                type="button"
                class="btn btn-sm btn-primary"
                @click="openManageModalForBeneficiary(card.beneficiary, 'unit')"
              >
                Añadir regalía
              </button>
            </div>
          </div>

          <div class="card-body p-0">
            <table class="table table-row-dashed table-striped table-condensed table-hover mb-0">
              <thead>
                <tr class="text-muted text-uppercase fs-8">
                  <th style="width: 80px;">ID</th>
                  <th>Origen</th>
                  <th>Fuente</th>
                  <th>Tipo</th>
                  <th style="width: 120px;">Comisión</th>
                  <th class="text-end" style="width: 140px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="regalia in card.regalias"
                  :key="regalia.id"
                >
                  <!-- ID origen -->
                  <td>
                    #{{ regalia.source_id }}
                  </td>

                  <!-- Tipo de origen -->
                  <td>
                    <span
                      v-if="regalia.source_type === 'user'"
                      class="badge rounded-pill text-bg-success"
                    >
                      Indirecta
                    </span>
                    <span
                      v-else-if="regalia.source_type === 'unit'"
                      class="badge rounded-pill text-bg-info"
                    >
                      Directa
                    </span>
                    <span
                      v-else
                      class="badge rounded-pill text-bg-secondary"
                    >
                      {{ regalia.source_type }}
                    </span>
                  </td>

                  <!-- Detalle origen -->
                  <td>
                    <!-- Origen usuario -->
                    <div v-if="regalia.origin_user">
                      <div class="fw-semibold d-flex align-items-center gap-2">
                        <span>{{ regalia.origin_user.display_name }}</span>
                        <span
                          v-if="regalia.origin_user.status && regalia.origin_user.status !== 'active'"
                          class="badge rounded-pill text-bg-danger"
                        >
                          {{ regalia.origin_user.status }}
                        </span>
                      </div>
                    </div>

                    <!-- Origen unidad -->
                    <div v-else-if="regalia.origin_unit">
                      <div class="fw-semibold d-flex align-items-center gap-2">
                        <span>{{ regalia.origin_unit.name }}</span>
                        <span
                          v-if="regalia.origin_unit.status && regalia.origin_unit.status !== 'active'"
                          class="badge rounded-pill text-bg-danger"
                        >
                          {{ regalia.origin_unit.status }}
                        </span>
                      </div>
                    </div>

                    <!-- Error: origen ausente -->
                    <div v-else class="text-danger small">
                      Error: origen de regalía no disponible.
                    </div>
                  </td>
                  <td>
                    <!-- Origen usuario -->
                    <div v-if="regalia.origin_user">
                        Regalía indirecta (usuario)
                    </div>

                    <!-- Origen unidad -->
                    <div v-else-if="regalia.origin_unit">
                        {{ regalia.origin_unit.type || 'error' }}
                    </div>
                  </td>

                  <!-- Comisión inline -->
                  <td>
                    <div class="input-group input-group-sm" v-if="can('regalia.users.edit')">
                      <input
                        type="text"
                        class="form-control text-end"
                        v-model="regalia._commissionInput"
                        inputmode="decimal"
                        autocomplete="off"
                        @input="onCommissionInput(regalia, $event)"
                      />
                      <span class="input-group-text">%</span>
                    </div>
                    <div v-else class="text-end">
                      <span>
                        {{ formatCommission(regalia.commission) }} %
                      </span>
                    </div>
                  </td>

                  <!-- Acciones -->
                  <td class="text-end">
                    <button
                      v-if="can('regalia.users.edit')"
                      type="button"
                      class="btn btn-sm btn-light-danger"
                      @click="confirmAndRemoveRegalia(card, regalia)"
                      :disabled="regalia._saving"
                    >
                      Quitar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Paginación beneficiarios -->
        <div
          class="d-flex align-items-center justify-content-between mt-3"
          v-if="meta.total > meta.per_page"
        >
          <div class="text-muted small">
            Mostrando {{ meta.from }}–{{ meta.to }} de {{ meta.total }}
          </div>
          <div class="btn-group">
            <button
              type="button"
              class="btn btn-sm btn-light"
              :disabled="meta.current_page <= 1 || loading"
              @click="goToPage(meta.current_page - 1)"
            >
              Anterior
            </button>
            <button
              type="button"
              class="btn btn-sm btn-light"
              :disabled="meta.current_page >= meta.last_page || loading"
              @click="goToPage(meta.current_page + 1)"
            >
              Siguiente
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal gestión de regalías -->
    <admin-regalias-modals-regalias-manage-modal
      ref="manageModal"
      :sources="regaliasSources"
      @changed="reloadCurrentPage"
    />
  </div>
</template>

<script>
import * as format from '@/utils/format';

/**
 * Construye un mensaje de error legible a partir de una respuesta de error de axios.
 * - Si hay data.errors (objeto de arrays), concatena todos los mensajes en líneas separadas.
 * - Si no, usa data.message si existe.
 * - Si nada de lo anterior existe, usa el fallback.
 */
function buildErrorMessage(e, fallbackMessage) {
  const data = e && e.response ? e.response.data : null;

  if (data && data.errors && typeof data.errors === 'object') {
    const lines = [];

    Object.keys(data.errors).forEach((field) => {
      const fieldErrors = data.errors[field];

      if (Array.isArray(fieldErrors)) {
        fieldErrors.forEach((msg) => {
          if (msg) {
            lines.push(String(msg));
          }
        });
      } else if (fieldErrors) {
        lines.push(String(fieldErrors));
      }
    });

    if (lines.length > 0) {
      return lines.join('\n');
    }
  }

  if (data && data.message) {
    return String(data.message);
  }

  return fallbackMessage;
}

export default {
  name: 'AdminRegaliasIndex',
  data() {
    return {
      loading: false,

      filters: {
        search: '',
        per_page: null,
      },
      searchTimer: null,

      beneficiaries: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: null,
        total: 0,
        from: 0,
        to: 0,
      },

      // Tipos de origen permitidos (user, unit, etc.) desde backend / APP_REGALIAS
      regaliasSources: [],

      autosaveDelay: null,
      autosaveTimers: {},
    };
  },
  mounted() {
    // Estas globales deben existir; si no, es un error de configuración
    if (typeof this.perPageShort !== 'number') {
      throw new Error('Config perPageShort no está definido en Vue globalProperties.');
    }
    if (typeof this.perPageMedium !== 'number') {
      throw new Error('Config perPageMedium no está definido en Vue globalProperties.');
    }
    if (typeof this.perPageLarge !== 'number') {
      throw new Error('Config perPageLarge no está definido en Vue globalProperties.');
    }
    if (typeof this.autosaveDelayMs !== 'number') {
      throw new Error('Config autosaveDelayMs no está definido en Vue globalProperties.');
    }

    this.filters.per_page = this.perPageLarge;
    this.meta.per_page = this.perPageLarge;
    this.autosaveDelay = this.autosaveDelayMs;

    this.reloadFirstPage();
  },
  methods: {
    // Helpers de permisos / rutas vienen del mixin global (this.can, this.route)

    hasSourceType(type) {
      if (!type) return false;

      if (!Array.isArray(this.regaliasSources)) {
        throw new Error('regaliasSources no inicializado correctamente.');
      }

      return this.regaliasSources.includes(type);
    },

    onSearchInput() {
      if (this.searchTimer) {
        clearTimeout(this.searchTimer);
      }

      this.searchTimer = setTimeout(() => {
        this.reloadFirstPage();
      }, 400);
    },

    reloadFirstPage() {
      this.fetchBeneficiaries(1);
    },

    reloadCurrentPage() {
      const page = this.meta.current_page || 1;
      this.fetchBeneficiaries(page);
    },

    async fetchBeneficiaries(page) {
      this.loading = true;

      try {
        const res = await axios.get(
          this.route('admin.regalias.api.beneficiaries.index'),
          {
            params: {
              page,
              per_page: this.filters.per_page,
              q: this.filters.search || '',
            },
          }
        );

        const payload = res?.data || {};
        const list = Array.isArray(payload.data) ? payload.data : [];

        const metaRoot = payload.meta || {};
        const metaSrc = metaRoot.pagination || metaRoot || {};

        const total =
          typeof metaSrc.total === 'number' ? metaSrc.total : list.length;
        const perPage =
          typeof metaSrc.per_page === 'number'
            ? metaSrc.per_page
            : this.filters.per_page;
        const currentPage =
          typeof metaSrc.current_page === 'number'
            ? metaSrc.current_page
            : page;
        const lastPage =
          typeof metaSrc.last_page === 'number' ? metaSrc.last_page : 1;

        let from = metaSrc.from;
        let to = metaSrc.to;

        if (!from || !to) {
          if (list.length === 0) {
            from = 0;
            to = 0;
          } else {
            from = (currentPage - 1) * perPage + 1;
            to = from + list.length - 1;
          }
        }

        this.meta = {
          current_page: currentPage,
          last_page: lastPage,
          per_page: perPage,
          total,
          from,
          to,
        };

        // regaliasSources: si el backend lo envía, lo usamos tal cual;
        // si viene vacío, dejamos []
        if ('regalias_sources' in metaRoot) {
          const sources = metaRoot.regalias_sources;
          if (Array.isArray(sources)) {
            this.regaliasSources = sources;
          } else {
            throw new Error('meta.regalias_sources debe ser un array.');
          }
        } else {
          this.regaliasSources = [];
        }

        this.beneficiaries = list.map((item) => {
          const beneficiary = item.beneficiary || {};
          const regalias = Array.isArray(item.regalias) ? item.regalias : [];

          return {
            beneficiary: {
              id: beneficiary.id,
              display_name: beneficiary.display_name || '',
              email: beneficiary.email || '',
              status: beneficiary.status || null,
            },
            regalias: regalias.map((reg) => this.mapRegalia(reg)),
          };
        });
      } catch (e) {
        this.beneficiaries = [];
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            buildErrorMessage(e, 'Error al cargar beneficiarios de regalías.'),
            'danger'
          );
        }
        // Deja que el error también sea visible en consola
        // para diagnóstico de configuración/datos.
        // eslint-disable-next-line no-console
        console.error(e);
      } finally {
        this.loading = false;
      }
    },

    mapRegalia(item) {
      if (!item) {
        return {
          id: null,
          source_id: null,
          source_type: null,
          commission: null,
          origin_user: null,
          origin_unit: null,
          _commissionInput: 'error',
          _lastValidCommissionInput: 'error',
          _savedCommissionNumeric: null,
          _savedCommissionInput: 'error',
          _saving: false,
        };
      }

      let numeric = null;
      let display = 'error';

      if (item.commission != null) {
        const n = Number(item.commission);
        if (Number.isFinite(n)) {
          const clamped = Math.max(0, Math.min(100, n));
          numeric = clamped;
          display = format.formatDecimal(clamped);
        }
      }

      // Si numeric sigue siendo null, consideramos que hay error de datos;
      // mostramos "error" en vez de dejarlo vacío.
      return {
        ...item,
        commission: numeric,
        origin_user: item.origin_user ?? null,
        origin_unit: item.origin_unit ?? null,
        _commissionInput: display,
        _lastValidCommissionInput: display,
        _savedCommissionNumeric: numeric,
        _savedCommissionInput: display,
        _saving: false,
      };
    },

    formatCommission(value) {
      if (value === null || value === undefined) return 'error';
      const num = Number(value);
      if (!Number.isFinite(num)) return 'error';
      return format.formatDecimal(num);
    },

    goToPage(page) {
      if (page < 1 || page > (this.meta.last_page || 1)) return;
      this.fetchBeneficiaries(page);
    },

    onCommissionInput(regalia, event) {
      if (!regalia) return;

      const raw =
        (event && event.target ? event.target.value : regalia._commissionInput) ||
        '';

      const { normalized, display } = format.normalizeDecimalDigitsInput(raw);

      let numeric = null;
      if (normalized !== null && normalized !== '' && normalized !== undefined) {
        const n = Number(normalized);
        if (!Number.isNaN(n)) {
          numeric = n;
        }
      }

      // Límite 0–100
      if (numeric !== null && numeric > 100) {
        const prev = regalia._lastValidCommissionInput || regalia._commissionInput || 'error';
        regalia._commissionInput = prev;
        if (event && event.target) {
          event.target.value = prev;
        }
        return;
      }

      regalia._commissionInput = display === '' ? 'error' : display;
      regalia._lastValidCommissionInput = regalia._commissionInput;
      regalia.commission = numeric;

      this.scheduleAutosave(regalia);
    },

    scheduleAutosave(regalia) {
      if (!regalia || !regalia.id) return;

      if (!this.autosaveTimers) {
        this.autosaveTimers = {};
      }

      const key = `regalia:${regalia.id}`;

      if (this.autosaveTimers[key]) {
        clearTimeout(this.autosaveTimers[key]);
      }

      this.autosaveTimers[key] = setTimeout(() => {
        this.autosaveTimers[key] = null;
        this.saveCommission(regalia);
      }, this.autosaveDelay);
    },

    async saveCommission(regalia) {
      if (!regalia || !regalia.id) return;

      const key = `regalia:${regalia.id}`;
      if (this.autosaveTimers && this.autosaveTimers[key]) {
        clearTimeout(this.autosaveTimers[key]);
        this.autosaveTimers[key] = null;
      }

      const prevNumeric = regalia._savedCommissionNumeric;
      const prevInput = regalia._savedCommissionInput;

      const value =
        typeof regalia.commission === 'number' ? regalia.commission : null;

      regalia._saving = true;

      try {
        await axios.patch(
          this.route('admin.regalias.api.regalias.update', {
            regalia: regalia.id,
          }),
          { commission: value },
          {
            headers: { 'Content-Type': 'application/json' },
          }
        );

        regalia._savedCommissionNumeric = regalia.commission;
        regalia._savedCommissionInput = regalia._commissionInput;

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash('Comisión guardada.', 'success');
        }
      } catch (e) {
        regalia.commission = prevNumeric;
        regalia._commissionInput = prevInput ?? 'error';
        regalia._lastValidCommissionInput = regalia._commissionInput;

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            buildErrorMessage(e, 'Error al guardar comisión.'),
            'danger'
          );
        }
        // eslint-disable-next-line no-console
        console.error(e);
      } finally {
        regalia._saving = false;
      }
    },

    async confirmAndRemoveRegalia(card, regalia) {
      if (!card || !regalia || !regalia.id) return;

      if (
        !window.confirm(
          '¿Seguro que deseas quitar esta regalía para este beneficiario?'
        )
      ) {
        return;
      }

      regalia._saving = true;

      try {
        const res = await axios.delete(
          this.route('admin.regalias.api.regalias.destroy', {
            regalia: regalia.id,
          })
        );

        card.regalias = card.regalias.filter((r) => r.id !== regalia.id);

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            res?.data?.message || 'Regalía eliminada.',
            'success'
          );
        }
      } catch (e) {
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            buildErrorMessage(e, 'Error al eliminar regalía.'),
            'danger'
          );
        }
        // eslint-disable-next-line no-console
        console.error(e);
      } finally {
        regalia._saving = false;
      }
    },

    openManageModal() {
      if (!this.$refs.manageModal) return;
      this.$refs.manageModal.open({
        sources: this.regaliasSources,
      });
    },

    openManageModalForBeneficiary(beneficiary, preferredSourceType = null) {
      if (!this.$refs.manageModal || !beneficiary) return;
      this.$refs.manageModal.open({
        beneficiary,
        preferredSourceType,
        sources: this.regaliasSources,
      });
    },
  },
};
</script>
