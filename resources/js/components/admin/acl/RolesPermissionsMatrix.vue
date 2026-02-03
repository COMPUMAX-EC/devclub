<!-- RUTA: resources/js/components/admin/acl/AdminAclRolesPermissionsMatrix.vue -->
<template>
  <div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div>
        <h2 class="card-title mb-1">
          Listado
        </h2>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <button
          type="button"
          class="btn btn-sm btn-light-primary"
          @click="openCreateRole"
        >
          <i class="ki-duotone ki-plus fs-5 me-1"></i>
          Nuevo rol
        </button>
        <button
          type="button"
          class="btn btn-sm btn-light"
          @click="openCreatePermission"
        >
          <i class="ki-duotone ki-plus fs-5 me-1"></i>
          Nuevo permiso
        </button>
      </div>
    </div>

    <div class="card-body p-0">
      <div v-if="loading" class="p-6 text-center">
        <span class="spinner-border" role="status"></span>
      </div>

      <div v-else>
        <!-- Filtro por prefijo de permission.name -->
        <div class="p-3 border-bottom">
          <div class="row g-2 align-items-center">
            <div class="col-auto">
              <label class="form-label mb-0 small text-muted">
                Filtrar permisos por prefijo
              </label>
            </div>
            <div class="col">
              <input
                type="text"
                class="form-control form-control-sm"
                v-model="permissionFilter"
                placeholder="Ej: unit.structure."
              >
            </div>
          </div>
        </div>

        <div class="matrix-wrapper">
          <table class="table table-sm align-middle mb-0 matrix-table table-striped table-condensed">
            <thead class="text-nowrap">
              <tr>
                <th class="matrix-header-permission">
                  <a href="#" @click="ver_descripciones = !ver_descripciones">
                    PERMISO
                    <i
                      class="bi"
                      :class="{
                        'bi-eye-fill': !ver_descripciones,
                        'bi-eye-slash-fill': ver_descripciones
                      }"
                    ></i>
                  </a>
                </th>

                <th
                  v-for="role in roles"
                  :key="role.id"
                  class="matrix-header-role text-center"
                  :class="{ 'matrix-col-hover': hoveredRoleId === role.id }"
                  @mouseenter="hoveredRoleId = role.id"
                  @mouseleave="hoveredRoleId = null"
                >
                  <span class="matrix-header-role-text">
                    <a href="#" @click.prevent="openEditRole(role)">
                      <i
                        class="bi bi-pencil"
                        style="display:inline-block;transform:rotate(180deg);transform-origin:center;"
                      ></i>&nbsp;&nbsp;{{ roleLabel(role) }}
                    </a>
                  </span>
                </th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="permission in filteredPermissions"
                :key="permission.id"
                :class="{ 'matrix-row-hover': hoveredPermissionId === permission.id }"
              >
                <!-- Columna descripción + name del permiso -->
                <td
                  class="matrix-permission-cell"
                  :class="{ 'matrix-row-hover': hoveredPermissionId === permission.id }"
                  @mouseenter="hoveredPermissionId = permission.id"
                  @mouseleave="hoveredPermissionId = null"
                >
                  <a href="#" @click.prevent="openEditPermission(permission)">
                    <i class="bi bi-pencil"></i> {{ permission.name }}
                  </a>
                  <span v-if="ver_descripciones" class="text-muted">&nbsp;{{ permission.description }}</span>
                </td>

                <!-- Matriz de checkboxes (guardado inmediato por celda) -->
                <td
                  v-for="role in roles"
                  :key="role.id"
                  class="matrix-cell text-center"
                  :class="{
                    'matrix-col-hover': hoveredRoleId === role.id,
                    'matrix-row-hover': hoveredPermissionId === permission.id
                  }"
                  @mouseenter="onCellEnter(role.id, permission.id)"
                  @mouseleave="onCellLeave"
                >
                  <div class="form-check form-check-sm form-check-custom justify-content-center">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :checked="hasPermission(role.id, permission.id)"
                      :disabled="isCellSaving(role.id, permission.id)"
                      @change="toggleCell(role.id, permission.id, $event.target.checked)"
                    >
                  </div>
                </td>
              </tr>

              <tr v-if="!filteredPermissions.length">
                <td colspan="100" class="text-center text-muted py-6">
                  No hay permisos configurados para este guard (o no hay coincidencias con el filtro).
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal rol -->
    <div v-if="showRoleModal">
      <div class="modal fade show d-block" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                {{ roleForm.id ? 'Editar rol' : 'Nuevo rol' }}
                <small class="text-muted ms-2">({{ guard }})</small>
              </h5>
              <button
                type="button"
                class="btn-close"
                @click="closeRoleModal"
              ></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Name (slug interno)</label>
                <input
                  type="text"
                  class="form-control"
                  v-model="roleForm.name"
                >
              </div>

              <div class="mb-3">
                <label class="form-label">Scope</label>
                <select
                  class="form-select"
                  v-model="roleForm.scope"
                >
                  <option value="system">system</option>
                  <option value="consolidator">consolidator</option>
                  <option value="agency">agency</option>
                  <option value="freelance">freelance</option>
                </select>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label">Label (ES)</label>
                  <input
                    type="text"
                    class="form-control"
                    v-model="roleForm.label.es"
                  >
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label">Label (EN)</label>
                  <input
                    type="text"
                    class="form-control"
                    v-model="roleForm.label.en"
                  >
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-light"
                @click="closeRoleModal"
              >
                Cancelar
              </button>
              <button
                type="button"
                class="btn btn-primary"
                :disabled="savingRole"
                @click="submitRole"
              >
                <span
                  v-if="savingRole"
                  class="spinner-border spinner-border-sm me-1"
                  role="status"
                ></span>
                Guardar
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-backdrop fade show"></div>
    </div>

    <!-- Modal permiso -->
    <div v-if="showPermissionModal">
      <div class="modal fade show d-block" tabindex="-1" role="dialog">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">
                {{ permissionForm.id ? 'Editar permiso' : 'Nuevo permiso' }}
                <small class="text-muted ms-2">({{ guard }})</small>
              </h5>
              <button
                type="button"
                class="btn-close"
                @click="closePermissionModal"
              ></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Name (slug interno)</label>
                <input
                  type="text"
                  class="form-control"
                  v-model="permissionForm.name"
                >
              </div>
              <div class="mb-3">
                <label class="form-label">Description (no traducible)</label>
                <input
                  type="text"
                  class="form-control"
                  v-model="permissionForm.description"
                  :placeholder="permissionForm.name"
                >
              </div>
            </div>
            <div class="modal-footer">
              <button
                type="button"
                class="btn btn-light"
                @click="closePermissionModal"
              >
                Cancelar
              </button>
              <button
                type="button"
                class="btn btn-primary"
                :disabled="savingPermission"
                @click="submitPermission"
              >
                <span
                  v-if="savingPermission"
                  class="spinner-border spinner-border-sm me-1"
                  role="status"
                ></span>
                Guardar
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-backdrop fade show"></div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AdminAclRolesPermissionsMatrix',

  props: {
    guard: {
      type: String,
      required: true,
    },
    locale: {
      type: String,
      default: 'es',
    },
  },

  data() {
    return {
      loading: false,
      ver_descripciones: false,

      roles: [],
      permissions: [],
      // matrix[roleId][permissionId] = true|false
      matrix: {},
      // estado de guardado por celda: cellSaving[roleId][permissionId] = true|false
      cellSaving: {},

      // Autosave inline de descripción de permisos
      permissionAutosaveTimers: {},
      permissionAutosaveOriginals: {},

      // Modal rol
      showRoleModal: false,
      savingRole: false,
      roleForm: {
        id: null,
        name: '',
        scope: 'system',
        label: {
          es: '',
          en: '',
        },
      },

      // Modal permiso
      showPermissionModal: false,
      savingPermission: false,
      permissionForm: {
        id: null,
        name: '',
        description: '',
      },

      // Columna y fila actualmente “hovered”
      hoveredRoleId: null,
      hoveredPermissionId: null,

      // Filtro por prefijo de nombre de permiso
      permissionFilter: '',
    };
  },

  computed: {
    guardLabel() {
      if (this.guard === 'admin') {
        return 'admin (backoffice)';
      }
      if (this.guard === 'customer') {
        return 'customer (portal cliente)';
      }
      return this.guard;
    },

    // Lista de permisos filtrados por prefijo de permission.name
    filteredPermissions() {
      const raw = this.permissionFilter != null ? String(this.permissionFilter).trim() : '';
      if (raw === '') {
        return this.permissions;
      }
      const prefix = raw.toLowerCase();

      return this.permissions.filter((p) => {
        const name = (p && p.name != null) ? String(p.name) : '';
        return name.toLowerCase().startsWith(prefix);
      });
    },
  },

  created() {
    this.loadMatrix();
  },

  beforeUnmount() {
    Object.keys(this.permissionAutosaveTimers).forEach((key) => {
      clearTimeout(this.permissionAutosaveTimers[key]);
    });
  },

  methods: {
    showToast(message, type = 'success') {
      if (typeof window !== 'undefined' && typeof window.flash === 'function') {
        window.flash(message, type);
      } else {
        if (type === 'error') {
          console.error(message);
        } else {
          console.log(message);
        }
      }
    },

    roleLabel(role) {
      if (role.label != null) {
        try {
          const translated = this.translate(role.label);
          if (translated) {
            return translated;
          }
        } catch (e) {
          // se cae abajo
        }
      }
      return this.toLabelCapitalized(role.name);
    },

    loadMatrix() {
      this.loading = true;

      const url = this.route('admin.acl.roles-permissions.matrix', {
        guard: this.guard,
      });

      window.axios
        .get(url)
        .then(({ data }) => {
          this.roles = data.roles || [];
          this.permissions = data.permissions || [];

          const incomingMatrix = data.matrix || {};
          const newMatrix = {};
          const newCellSaving = {};

          this.roles.forEach((role) => {
            const roleId = role.id;
            newMatrix[roleId] = {};
            newCellSaving[roleId] = {};

            const permIds = incomingMatrix[roleId] || [];
            permIds.forEach((permId) => {
              newMatrix[roleId][permId] = true;
              newCellSaving[roleId][permId] = false;
            });
          });

          this.matrix = newMatrix;
          this.cellSaving = newCellSaving;
        })
        .catch((error) => {
          console.error(error);
          this.showToast('Error cargando los datos de roles y permisos.', 'danger');
        })
        .finally(() => {
          this.loading = false;
        });
    },

    hasPermission(roleId, permissionId) {
      return !!(this.matrix[roleId] && this.matrix[roleId][permissionId]);
    },

    isCellSaving(roleId, permissionId) {
      return !!(this.cellSaving[roleId] && this.cellSaving[roleId][permissionId]);
    },

    toggleCell(roleId, permissionId, checked) {
      const previous = this.hasPermission(roleId, permissionId);

      if (!this.matrix[roleId]) {
        this.matrix[roleId] = {};
      }
      this.matrix[roleId][permissionId] = checked;

      if (!this.cellSaving[roleId]) {
        this.cellSaving[roleId] = {};
      }
      this.cellSaving[roleId][permissionId] = true;

      const url = this.route('admin.acl.roles-permissions.toggle', {
        guard: this.guard,
      });

      const payload = {
        role_id: roleId,
        permission_id: permissionId,
        value: !!checked,
      };

      window.axios
        .post(url, payload)
        .then(({ data }) => {
          this.showToast(data.message || 'Permiso actualizado correctamente.');
        })
        .catch((error) => {
          console.error(error);
          if (!this.matrix[roleId]) {
            this.matrix[roleId] = {};
          }
          this.matrix[roleId][permissionId] = previous;

          this.showToast('Error actualizando el permiso del rol.', 'danger');
        })
        .finally(() => {
          if (!this.cellSaving[roleId]) {
            this.cellSaving[roleId] = {};
          }
          this.cellSaving[roleId][permissionId] = false;
        });
    },

    // hover de celda: marca fila y columna
    onCellEnter(roleId, permissionId) {
      this.hoveredRoleId = roleId;
      this.hoveredPermissionId = permissionId;
    },

    onCellLeave() {
      this.hoveredRoleId = null;
      this.hoveredPermissionId = null;
    },

    // -------------------------
    // Rol: modal y envío
    // -------------------------

    resetRoleForm() {
      this.roleForm = {
        id: null,
        name: '',
        scope: 'system',
        label: {
          es: '',
          en: '',
        },
      };
    },

    openCreateRole() {
      this.resetRoleForm();
      this.showRoleModal = true;
    },

    openEditRole(role) {
      this.roleForm = {
        id: role.id,
        name: role.name,
        scope: role.scope || 'system',
        label: {
          ...(role.label || {}),
        },
      };
      if (!this.roleForm.label.es) {
        this.roleForm.label.es = '';
      }
      if (!this.roleForm.label.en) {
        this.roleForm.label.en = '';
      }
      this.showRoleModal = true;
    },

    closeRoleModal() {
      this.showRoleModal = false;
      this.resetRoleForm();
    },

    submitRole() {
      this.savingRole = true;

      const payload = {
        name: this.roleForm.name,
        scope: this.roleForm.scope,
        label: this.roleForm.label,
      };

      let request;
      if (this.roleForm.id) {
        const url = this.route('admin.acl.roles-permissions.roles.update', {
          guard: this.guard,
          role: this.roleForm.id,
        });
        request = window.axios.put(url, payload);
      } else {
        const url = this.route('admin.acl.roles-permissions.roles.store', {
          guard: this.guard,
        });
        request = window.axios.post(url, payload);
      }

      request
        .then(() => {
          this.showToast('Rol guardado correctamente.');
          this.closeRoleModal();
          this.loadMatrix();
        })
        .catch((error) => {
          console.error(error);
          this.showToast('Error guardando el rol.', 'danger');
        })
        .finally(() => {
          this.savingRole = false;
        });
    },

    // -------------------------
    // Permiso: modal y envío
    // -------------------------

    resetPermissionForm() {
      this.permissionForm = {
        id: null,
        name: '',
        description: '',
      };
    },

    openCreatePermission() {
      this.resetPermissionForm();
      this.showPermissionModal = true;
    },

    openEditPermission(permission) {
      this.permissionForm = {
        id: permission.id,
        name: permission.name,
        description: permission.description || '',
      };
      this.showPermissionModal = true;
    },

    closePermissionModal() {
      this.showPermissionModal = false;
      this.resetPermissionForm();
    },

    submitPermission() {
      this.savingPermission = true;

      const payload = {
        name: this.permissionForm.name,
        description: this.permissionForm.description,
      };

      let request;
      if (this.permissionForm.id) {
        const url = this.route('admin.acl.roles-permissions.permissions.update', {
          guard: this.guard,
          permission: this.permissionForm.id,
        });
        request = window.axios.put(url, payload);
      } else {
        const url = this.route('admin.acl.roles-permissions.permissions.store', {
          guard: this.guard,
        });
        request = window.axios.post(url, payload);
      }

      request
        .then(() => {
          this.showToast('Permiso guardado correctamente.');
          this.closePermissionModal();
          this.loadMatrix();
        })
        .catch((error) => {
          console.error(error);
          this.showToast('Error guardando el permiso.', 'danger');
        })
        .finally(() => {
          this.savingPermission = false;
        });
    },

    toLabelCapitalized(text) {
      return String(text ?? '')
        .replace(/\./g, ' - ')
        .replace(/_/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase()
        .replace(/\b(\p{L})/gu, (m, letter) => letter.toUpperCase());
    },
  },
};
</script>

<style scoped>
/*.matrix-wrapper {
  max-height: 70vh;
  overflow: auto;
}*/

/* Para que sticky funcione con scroll */
.matrix-table {
  border-collapse: separate;
  border-spacing: 0;
}

/* Cabecera fila superior */
.matrix-header-permission,
.matrix-header-role,
.matrix-header-actions {
  position: sticky;
  top: 0;
  z-index: 2;
  background-color: #fff;
  text-transform: none !important;
  font-weight: normal!important;
}

/* Primera columna (permiso) sticky también a la izquierda */
.matrix-header-permission {
  left: 0;
  z-index: 4;
  background-color: #fff;
}

.matrix-permission-cell {
  position: sticky;
  left: 0;
  background-color: #fff;
  z-index: 3;
}

/* Cabeceras de roles verticales alineadas al bottom */
.matrix-header-role {
  height: 160px;
  padding: 0 4px;
  vertical-align: bottom;
}

.matrix-header-role-text {
  display: inline-block;
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  color: #7e8299; /* gris parecido a Craft */
}

/* Celdas normales */
.matrix-cell {
  text-align: center;
}

/* Columna acciones (si se usa) */
.matrix-header-actions {
  background-color: #fff;
}

.matrix-actions-cell {
  background-color: #fff;
}

/* Sombreado de columna en hover */
.matrix-col-hover {
  background-color: #ffc !important;
  transition: background-color 0.12sease-in-out;
  box-shadow: none;
}

/* Sombreado de fila en hover */
.matrix-row-hover td,
.matrix-row-hover.matrix-permission-cell,
.matrix-row-hover.matrix-cell {
  background-color: #ffc !important;
  box-shadow: none;
}

/* Asegurar que la primera columna también tome el color de fila */
.matrix-permission-cell.matrix-row-hover {
  background-color: #ffc !important;
}
</style>
