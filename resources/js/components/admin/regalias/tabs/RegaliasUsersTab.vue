<!-- resources/js/components/admin/regalias/tabs/RegaliasUsersTab.vue -->
<template>
  <div>
    <!-- Buscador simple por nombre/correo -->
    <div class="d-flex mb-3 gap-2 flex-wrap">
      <input
        type="search"
        class="form-control"
        style="max-width: 360px;"
        placeholder="Buscar usuarios por nombre o correo…"
        v-model="search"
        @input="onSearchInput"
      />
    </div>

    <div v-if="loading" class="text-muted">
      Cargando usuarios…
    </div>

    <div v-else>
      <div v-if="!rows.length" class="text-muted">
        No se encontraron usuarios.
      </div>

      <div v-else>
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr class="text-muted text-uppercase fs-8">
              <th style="width: 80px;">ID</th>
              <th>Nombre</th>
              <th>Email</th>
              <th style="width: 140px;">Estatus</th>
              <th style="width: 160px;">Regalía actual</th>
              <th class="text-end" style="width: 160px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>#{{ row.id }}</td>
              <td>{{ row.display_name || '—' }}</td>
              <td>{{ row.email || '—' }}</td>
              <td>
                <span
                  v-if="row.status === 'active'"
                  class="badge rounded-pill text-bg-success"
                >
                  activo
                </span>
                <span
                  v-else-if="row.status"
                  class="badge rounded-pill text-bg-secondary"
                >
                  {{ row.status }}
                </span>
                <span v-else class="badge rounded-pill text-bg-secondary">
                  sin estatus
                </span>
              </td>
              <td>
                <span v-if="row.is_assigned">
                  {{ row.commission_display }} %
                </span>
                <span v-else class="text-muted">—</span>
              </td>
              <td class="text-end">
                <button
                  v-if="!row.is_assigned"
                  type="button"
                  class="btn btn-sm btn-primary"
                  @click="attach(row)"
                  :disabled="row._loading"
                >
                  Añadir
                </button>
                <button
                  v-else
                  type="button"
                  class="btn btn-sm btn-light-danger"
                  @click="detach(row)"
                  :disabled="row._loading"
                >
                  Quitar
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div
          class="d-flex align-items-center justify-content-between mt-3"
          v-if="meta && meta.total && meta.per_page && meta.total > meta.per_page"
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
  </div>
</template>

<script>
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
  name: 'AdminRegaliasTabsRegaliasUsersTab',
  props: {
    beneficiaryId: {
      type: [Number, String],
      required: true,
    },
    perPage: {
      type: Number,
      default: null,
    },
  },
  data() {
    const runtime =
      (typeof window !== 'undefined' && window.__RUNTIME_CONFIG__) || {};

    return {
      loading: false,
      search: '',
      searchTimer: null,
      rows: [],
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: runtime.perPageMedium, // si falta en runtime se ve el problema
        total: null,
        from: null,
        to: null,
      },
    };
  },
  mounted() {
    if (this.perPage) {
      this.meta.per_page = this.perPage;
    }
    this.reloadFirstPage();
  },
  watch: {
    beneficiaryId() {
      this.reloadFirstPage();
    },
  },
  methods: {
    reloadFirstPage() {
      this.fetchPage(1);
    },

    onSearchInput() {
      if (this.searchTimer) {
        clearTimeout(this.searchTimer);
      }
      this.searchTimer = setTimeout(() => {
        this.reloadFirstPage();
      }, 400);
    },

    async fetchPage(page) {
      if (!this.beneficiaryId) return;

      this.loading = true;

      try {
        const res = await axios.get(
          this.route(
            'admin.regalias.api.beneficiaries.origins.users.available',
            { beneficiary: this.beneficiaryId }
          ),
          {
            params: {
              page,
              per_page: this.meta.per_page,
              q: this.search || '',
              status: 'active',
            },
          }
        );

        const payload = res?.data || {};
        let list = Array.isArray(payload.data) ? payload.data : [];

        // Excluir beneficiario como origen (no puedes generarte regalía a ti mismo)
        const beneficiaryIdNum = Number(this.beneficiaryId);
        list = list.filter((row) => Number(row.id) !== beneficiaryIdNum);

        const metaRoot = payload.meta || {};
        const metaSrc = metaRoot.pagination || metaRoot;

        this.meta = {
          current_page: metaSrc.current_page,
          last_page: metaSrc.last_page,
          per_page: metaSrc.per_page ?? this.meta.per_page,
          total: metaSrc.total,
          from: metaSrc.from,
          to: metaSrc.to,
        };

        this.rows = list.map((row) => {
          let numeric = null;

          if (row.commission_percent != null) {
            numeric = Number(row.commission_percent);
          } else if (row.commission != null) {
            numeric = Number(row.commission);
          }

          const commissionDisplay =
            numeric != null && !Number.isNaN(numeric) ? String(numeric) : '';

          return {
            id: row.id,
            display_name: row.display_name,
            email: row.email,
            status: row.status,
            is_assigned: !!row.is_assigned,
            regalia_id: row.regalia_id || null,
            commission: numeric,
            commission_display: commissionDisplay,
            _loading: false,
          };
        });
      } catch (e) {
        this.rows = [];
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            buildErrorMessage(
              e,
              'Error al cargar usuarios disponibles para regalías.'
            ),
            'danger'
          );
        }
      } finally {
        this.loading = false;
      }
    },

    goToPage(page) {
      if (!this.meta || !this.meta.last_page) return;
      if (page < 1 || page > this.meta.last_page) return;
      this.fetchPage(page);
    },

    async attach(row) {
      if (!row || !this.beneficiaryId) return;

      row._loading = true;

      try {
        const res = await axios.post(
          this.route('admin.regalias.api.regalias.store'),
          {
            // nombres alineados con el backend
            beneficiary_user_id: this.beneficiaryId,
            source_type: 'user',
            source_id: row.id,
          }
        );

        const payload = res?.data?.data || null;

        row.is_assigned = true;
        row.regalia_id = payload ? payload.id : row.regalia_id;

        if (payload && payload.commission_percent != null) {
          row.commission = Number(payload.commission_percent);
        }

        row.commission_display =
          row.commission != null ? String(row.commission) : '';

        this.$emit('changed');

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            res?.data?.message || 'Regalía creada para este usuario.',
            'success'
          );
        }
      } catch (e) {
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            buildErrorMessage(e, 'Error al crear regalía.'),
            'danger'
          );
        }
      } finally {
        row._loading = false;
      }
    },

    async detach(row) {
      if (!row || !row.regalia_id) return;

      if (
        !window.confirm(
          '¿Quitar esta regalía entre el beneficiario y este usuario?'
        )
      ) {
        return;
      }

      row._loading = true;

      try {
        const res = await axios.delete(
          this.route('admin.regalias.api.regalias.destroy', {
            regalia: row.regalia_id,
          })
        );

        row.is_assigned = false;
        row.regalia_id = null;
        row.commission = null;
        row.commission_display = '';

        this.$emit('changed');

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            res?.data?.message || 'Regalía removida.',
            'success'
          );
        }
      } catch (e) {
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            buildErrorMessage(e, 'Error al remover regalía.'),
            'danger'
          );
        }
      } finally {
        row._loading = false;
      }
    },
  },
};
</script>
