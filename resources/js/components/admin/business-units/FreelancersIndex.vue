<!-- resources/js/components/admin/business-units/FreelancersIndex.vue -->
<template>
  <div class="card">
    <div class="card-header align-items-center">
      <div class="card-title flex-column">
        <h3 class="card-label fw-bold mb-1">Freelances</h3>
        <div class="text-muted small">
          Unidades tipo <code>freelance</code> (root)
        </div>
      </div>

      <div class="card-toolbar d-flex flex-wrap gap-3 justify-content-end">
        <!-- Acciones de creación / vinculación agrupadas -->
        <div class="d-flex gap-2">
          <div class="dropdown">
            <button
              type="button"
              class="btn btn-primary dropdown-toggle"
              data-bs-toggle="dropdown"
              :disabled="!permissions.can_create || loading"
            >
              Añadir
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <button
                  type="button"
                  class="dropdown-item"
                  @click="openCreateFreelance"
                >
                  Crear freelance
                </button>
              </li>
              <li>
                <button
                  type="button"
                  class="dropdown-item"
                  @click="openAddExistingFreelance"
                >
                  Añadir existente
                </button>
              </li>
            </ul>
          </div>
        </div>

        <!-- Filtros -->
        <div class="d-flex gap-2 align-items-center">
          <div class="w-250px">
            <input
              v-model="filters.q"
              type="text"
              class="form-control form-control-solid"
              placeholder="Buscar por nombre / apellido / email"
              @keyup.enter="fetchUnits(1)"
            />
          </div>

          <select
            v-model="filters.status"
            class="form-select form-select-solid w-150px"
            @change="fetchUnits(1)"
          >
            <option value="active">Activos</option>
            <option value="inactive">Inactivos</option>
            <option value="all">Todos</option>
          </select>

          <button
            class="btn btn-light"
            @click="fetchUnits(1)"
            :disabled="loading"
          >
            Refrescar
          </button>
        </div>
      </div>
    </div>

    <div class="card-body py-3">
      <div v-if="error" class="alert alert-danger py-2">
        {{ error }}
      </div>

      <div class="table-responsive">
        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
          <thead>
            <tr class="fw-bold text-muted">
              <th class="min-w-250px">Nombre</th>
              <th class="min-w-150px">Estado de la unidad</th>
              <th class="min-w-150px text-end">Acciones</th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="!loading && units.length === 0">
              <td colspan="3" class="text-center text-muted py-8">
                Sin resultados
              </td>
            </tr>

            <tr v-for="u in units" :key="u.id">
              <td>
                <div class="d-flex flex-column">
                  <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold">
                      {{ ownerName(u) }}
                    </span>

                    <span
                      v-if="u.owner_user && u.owner_user.status && u.owner_user.status !== 'active'"
                      class="badge badge-light-warning"
                      :title="'Estatus usuario: ' + u.owner_user.status"
                    >
                      {{ u.owner_user.status }}
                    </span>

                    <span
                      v-if="!u.owner_user"
                      class="badge badge-light-danger"
                      title="Sin usuario vinculado"
                    >
                      sin usuario
                    </span>
                  </div>

                  <div
                    class="text-muted small"
                    v-if="u.owner_user && u.owner_user.email"
                  >
                    {{ u.owner_user.email }}
                  </div>
                </div>
              </td>

              <td>
                <span :class="statusBadge(u.status)">
                  {{ u.status }}
                </span>
              </td>

              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a :href="unitUrl(u)" class="btn btn-sm btn-primary">
                    Enter
                  </a>

                  <button
                    v-if="canToggleStatus(u)"
                    class="btn btn-sm btn-light"
                    @click.prevent="toggleStatus(u)"
                    :disabled="isToggleDisabled(u)"
                    :title="toggleTitle(u)"
                  >
                    <span v-if="isBusy(u)">Procesando...</span>
                    <span v-else>
                      {{ u.status === 'active' ? 'Suspender' : 'Activar' }}
                    </span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        class="d-flex justify-content-between align-items-center mt-4"
        v-if="meta.pagination"
      >
        <div class="text-muted small">
          Página {{ meta.pagination.current_page }} de
          {{ meta.pagination.last_page }} · Total
          {{ meta.pagination.total }}
        </div>

        <div class="btn-group">
          <button
            class="btn btn-sm btn-light"
            @click="fetchUnits(meta.pagination.current_page - 1)"
            :disabled="loading || meta.pagination.current_page <= 1"
          >
            Anterior
          </button>
          <button
            class="btn btn-sm btn-light"
            @click="fetchUnits(meta.pagination.current_page + 1)"
            :disabled="loading || meta.pagination.current_page >= meta.pagination.last_page"
          >
            Siguiente
          </button>
        </div>
      </div>
    </div>

    <!-- Modales para creación/vinculación de freelances -->
    <admin-business-units-modals-create-user-modal
      ref="createUserModal"
      unit-type="freelance"
      :unit-id="null"
      @memberships-created="onMembershipsCreated"
    />

    <admin-business-units-modals-pick-active-user-modal
      ref="pickActiveUserModal"
      @confirm="onConfirmPickUser"
    />

    <admin-business-units-modals-link-user-by-email-modal
      ref="linkByEmailModal"
      @confirm="onConfirmEmailExact"
    />
  </div>
</template>

<script>
import axios from 'axios'

const unitsApi = {
  list: (params) => axios.get(route('admin.business-units.api.units'), { params }),
}

export default {
  name: 'AdminBusinessUnitsFreelancersIndex',

  data() {
    return {
      loading: false,
      error: null,
      units: [],
      meta: {},
      filters: {
        q: '',
        status: 'active',
      },
      busyByUnitId: {}, // { [id]: true }
      permissions: {
        can_create: false,
        can_pick_active_users: false,
      },
    }
  },

  mounted() {
    this.fetchUnits(1)
  },

  methods: {
    ownerName(u) {
      if (u && u.owner_user && u.owner_user.display_name) return u.owner_user.display_name
      if (u && u.owner_user && (u.owner_user.first_name || u.owner_user.last_name)) {
        return `${u.owner_user.first_name || ''} ${u.owner_user.last_name || ''}`.trim()
      }
      return '—'
    },

    statusBadge(status) {
      if (status === 'active') return 'badge badge-light-success'
      if (status === 'inactive') return 'badge badge-light-danger'
      return 'badge badge-light'
    },

    unitUrl(u) {
      return route('admin.business-units.show', { unit: u.id })
    },

    isBusy(u) {
      const id = u?.id
      return !!(id && this.busyByUnitId[id])
    },

    canToggleStatus(u) {
      const a = u?.abilities
      if (!a) return true

      const keys = ['can_toggle_status', 'toggle_status', 'toggleStatus', 'canToggleStatus']
      let hasAnyKey = false

      for (const k of keys) {
        if (Object.prototype.hasOwnProperty.call(a, k)) {
          hasAnyKey = true
          if (a[k] === true) return true
        }
      }

      // Si viene abilities pero no trae ninguna de estas llaves, no bloqueamos el UI.
      // Si trae alguna de estas llaves y ninguna es true, bloqueamos.
      return !hasAnyKey
    },

    isToggleDisabled(u) {
      return this.isBusy(u) || !this.canToggleStatus(u)
    },

    toggleTitle(u) {
      if (this.isBusy(u)) return 'Procesando...'
      if (!this.canToggleStatus(u)) return 'Sin permisos'
      return ''
    },

    baseUnitsApiUrl() {
      try {
        return route('admin.business-units.api.units')
      } catch (e) {
        return '/admin/business-units/api/units'
      }
    },

    toggleStatusUrl(u) {
      // Preferir URLs explícitas si el backend las entrega
      const a = u?.abilities || {}
      if (a.toggle_status_url) return a.toggle_status_url
      if (a.toggleStatusUrl) return a.toggleStatusUrl
      if (u?.toggle_status_url) return u.toggle_status_url
      if (u?.toggleStatusUrl) return u.toggleStatusUrl

      // Fallback basado en el endpoint RESTful de estado
      const base = String(this.baseUnitsApiUrl()).replace(/\/+$/, '')
      return `${base}/${u.id}/status`
    },

    async fetchUnits(page) {
      this.loading = true
      this.error = null

      try {
        const { data } = await unitsApi.list({
          type: 'freelance',
          root: true,
          status: this.filters.status,
          q: this.filters.q || null,
          page: page || 1,
          per_page: 25,
        })

        this.units = data.data || []
        this.meta = data.meta || {}

        if (data.meta && data.meta.permissions) {
          this.permissions = {
            ...this.permissions,
            ...data.meta.permissions,
          }
        }
      } catch (e) {
        const msg = this.humanError(e) || 'Error cargando freelances'
        this.error = msg
        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash(msg, 'danger')
        }
      } finally {
        this.loading = false
      }
    },

    async toggleStatus(u) {
      const id = u?.id
      if (!id) return
      if (!this.canToggleStatus(u)) return
      if (this.isBusy(u)) return

      const nextStatus = u.status === 'active' ? 'inactive' : 'active'
      const actionLabel = nextStatus === 'active' ? 'activar' : 'suspender'

      const ok = window.confirm(`¿Confirmas ${actionLabel} esta unidad?`)
      if (!ok) return

      this.$set?.(this.busyByUnitId, id, true) || (this.busyByUnitId[id] = true)

      try {
        const url = this.toggleStatusUrl(u)

        // Enviamos el estado destino al endpoint RESTful de status
        await axios.patch(url, { status: nextStatus })

        // UI optimista: solo actualizamos el objeto en memoria, sin refrescar por AJAX
        u.status = nextStatus

        if (typeof window.flash === 'function') {
          window.flash(
            `Unidad ${nextStatus === 'active' ? 'activada' : 'suspendida'} correctamente.`,
            'success'
          )
        }
      } catch (e) {
        const msg = this.humanError(e) || 'No se pudo actualizar el estado.'
        if (typeof window.flash === 'function') {
          window.flash(msg, 'danger')
        }
      } finally {
        this.$set?.(this.busyByUnitId, id, false) || (this.busyByUnitId[id] = false)
      }
    },

    // -------- Flujos de creación/vinculación de freelance --------

    openCreateFreelance() {
      if (!this.permissions.can_create) return
      if (this.$refs.createUserModal && typeof this.$refs.createUserModal.open === 'function') {
        this.$refs.createUserModal.open()
      }
    },

    openAddExistingFreelance() {
      if (!this.permissions.can_create) return

      if (this.permissions.can_pick_active_users) {
        if (this.$refs.pickActiveUserModal && typeof this.$refs.pickActiveUserModal.open === 'function') {
          this.$refs.pickActiveUserModal.open()
        }
      } else {
        if (this.$refs.linkByEmailModal && typeof this.$refs.linkByEmailModal.open === 'function') {
          this.$refs.linkByEmailModal.open()
        }
      }
    },

    onMembershipsCreated() {
      // Tras crear un nuevo freelance, recargamos el listado.
      this.fetchUnits(1)
    },

    async onConfirmPickUser(payload) {
      try {
        await axios.post(this.route('admin.business-units.api.units.store'), {
          type: 'freelance',
          mode: 'existing_user',
          existing_user_id: payload.user_id,
        })
        if (typeof window.flash === 'function') {
          window.flash('Freelance creado.', 'success')
        }
        await this.fetchUnits(1)
      } catch (e) {
        const msg = this.humanError(e)
        if (typeof window.flash === 'function') {
          window.flash(msg, 'danger')
        }
      }
    },

    async onConfirmEmailExact(payload) {
      try {
        await axios.post(this.route('admin.business-units.api.units.store'), {
          type: 'freelance',
          mode: 'email_exact',
          email: payload.email,
        })
        if (typeof window.flash === 'function') {
          window.flash('Freelance creado.', 'success')
        }
        await this.fetchUnits(1)
      } catch (e) {
        const msg = this.humanError(e)
        if (typeof window.flash === 'function') {
          window.flash(msg, 'danger')
        }
      }
    },

    humanError(e) {
      if (!e) return 'Error'
      const r = e.response
      if (r && r.data) {
        if (r.data.message) return r.data.message
        if (r.data.errors) {
          try {
            const all = Object.values(r.data.errors).flat()
            if (all.length) return all.join(' ')
          } catch (_) {
            // ignore
          }
        }
      }
      return e.message || 'Error'
    },
  },
}
</script>
