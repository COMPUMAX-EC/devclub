<!-- resources/js/components/ui/UserSearchSelector.vue -->
<template>
  <div>
    <div class="d-flex mb-3 gap-2 flex-wrap">
      <input
        type="search"
        class="form-control"
        style="max-width: 360px;"
        placeholder="Buscar usuarios por nombre o correo…"
        v-model="search"
        @input="onSearchInput"
      />

      <!-- Selector de estado solo si NO hay status fijo -->
      <select
        v-if="!hasFixedStatus"
        class="form-select"
        style="max-width: 180px;"
        v-model="statusLocal"
        @change="reloadFirstPage"
      >
        <option value="">Todos los estatus</option>
        <option value="active">Solo activos</option>
        <option value="suspended">Suspendidos</option>
        <option value="locked">Bloqueados</option>
      </select>
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
              <th class="text-end" style="width: 140px;">Acciones</th>
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
                <span v-else class="text-muted">—</span>
              </td>
              <td class="text-end">
                <button
                  type="button"
                  class="btn btn-sm btn-primary"
                  @click="selectRow(row)"
                >
                  Seleccionar
                </button>
              </td>
            </tr>
          </tbody>
        </table>

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
  </div>
</template>

<script>
export default {
  name: 'UserSearchSelector',
  props: {
    perPage: {
      type: Number,
      default: () => {
        const runtime =
          (typeof window !== 'undefined' && window.__RUNTIME_CONFIG__) || {};
        return runtime.perPageMedium ?? 10;
      },
    },
    /**
     * Status fijo a usar en el backend:
     *  - Si viene vacío: se muestra el selector y el usuario puede cambiar status.
     *  - Si viene con valor (ej: "active"): se usa siempre ese valor y no se muestra el selector.
     */
    status: {
      type: String,
      default: '',
    },
  },
  data() {
    const runtime =
      (typeof window !== 'undefined' && window.__RUNTIME_CONFIG__) || {};

    return {
      loading: false,
      rows: [],
      search: '',
      // Filtro de estado editable solo cuando NO hay status fijo por prop
      statusLocal: this.status || '',
      searchTimer: null,
      meta: {
        current_page: 1,
        last_page: 1,
        per_page: runtime.perPageMedium ?? 10,
        total: 0,
        from: 0,
        to: 0,
      },
    };
  },
  computed: {
    // True cuando el componente tiene un status fijo definido por prop
    hasFixedStatus() {
      return this.status !== null && this.status !== undefined && this.status !== '';
    },
  },
  mounted() {
    this.meta.per_page = this.perPage;
    this.reloadFirstPage();
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
      this.loading = true;

      try {
        const res = await axios.get(
          this.route('admin.users.api.search'),
          {
            params: {
              page,
              per_page: this.meta.per_page,
              q: this.search || '',
              // Si hay status fijo, siempre se usa el de la prop; si no, el local
              status: this.hasFixedStatus ? this.status : this.statusLocal,
            },
          }
        );

        const payload = res?.data || {};
        const list = Array.isArray(payload.data) ? payload.data : [];

        const metaSrc = payload.meta?.pagination || payload.meta || {};

        const total =
          typeof metaSrc.total === 'number' ? metaSrc.total : list.length;
        const perPage =
          typeof metaSrc.per_page === 'number'
            ? metaSrc.per_page
            : this.meta.per_page;
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

        this.rows = list.map((row) => ({
          id: row.id,
          display_name: row.display_name,
          email: row.email,
          status: row.status,
        }));
      } catch (e) {
        this.rows = [];
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(
            e?.response?.data?.message ||
              'Error al buscar usuarios.',
            'danger'
          );
        }
      } finally {
        this.loading = false;
      }
    },

    goToPage(page) {
      if (page < 1 || page > (this.meta.last_page || 1)) return;
      this.fetchPage(page);
    },

    selectRow(row) {
      if (!row || !row.id) return;
      this.$emit('selected', row);
    },
  },
};
</script>
