<!-- resources/js/components/admin/companies/Edit.vue -->
<template>
  <div class="container-fluid">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-6">
      <div>
        <h1 class="fs-2hx fw-bold mb-1">
          <span v-if="company">
            {{ company.name }}
          </span>
          <span v-else>
            Empresa
          </span>

          <span
            v-if="company"
            class="badge ms-3"
            :class="statusBadgeClass(company.status)"
          >
            {{ company.status_label }}
          </span>
        </h1>
      </div>
    </div>

    <div v-if="isLoading" class="text-center py-10">
      <div class="spinner-border" role="status">
        <span class="visually-hidden">Cargando...</span>
      </div>
    </div>

    <div v-else>
      <!-- Datos básicos -->
      <div class="card mb-7">
        <div class="card-header">
          <h3 class="card-title fw-bold">Datos básicos</h3>
          <div class="card-toolbar">
            <span class="text-muted fs-7">
              Los cambios se guardan automáticamente.
            </span>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-5">
            <div class="col-md-6">
              <label class="form-label">Nombre de la empresa</label>
              <input
                v-model="formBasic.name"
                type="text"
                class="form-control"
                autocomplete="off"
              />
            </div>

            <div class="col-md-6">
              <label class="form-label">Código único</label>
              <div class="input-group">
                <input
                  v-model="formBasic.short_code"
                  type="text"
                  class="form-control text-uppercase"
                  maxlength="5"
                  autocomplete="off"
                  :class="{
                    'is-invalid': shortCodeStatus === 'taken',
                    'is-valid': shortCodeStatus === 'available'
                  }"
                />
              </div>
              <div v-if="shortCodeMessage" class="form-text text-muted">
                {{ shortCodeMessage }}
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Teléfono</label>
              <input
                v-model="formBasic.phone"
                type="text"
                class="form-control"
                autocomplete="off"
              />
            </div>

            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input
                v-model="formBasic.email"
                type="email"
                class="form-control"
                autocomplete="off"
              />
            </div>

            <div class="col-12">
              <label class="form-label">Descripción</label>
              <textarea
                v-model="formBasic.description"
                class="form-control"
                rows="3"
              ></textarea>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Estatus</label>
              <select
                v-model="formBasic.status"
                class="form-select"
              >
                <option value="active">Activa</option>
                <option value="inactive">Suspendida</option>
                <option value="archived">Archivada</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Usuarios y comisiones -->
      <div class="card mb-7">
        <div class="card-header">
          <h4 class="card-title fw-bold">Usuarios que pueden operar en la empresa</h4>
          <div class="card-toolbar">
            <button
              type="button"
              class="btn btn-sm btn-primary"
              @click="openUsersModal"
            >
              + Añadir
            </button>
          </div>
        </div>

        <div class="card-body">
          <!-- Usuarios asignados -->
          <table class="table table-row-dashed table-striped table-hover table-condensed mb-6">
            <thead>
              <tr>
                <th style="width: 80px;">ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th class="text-end" style="width: 140px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="user in assignedUsers"
                :key="'assigned-' + user.id"
              >
                <td>#{{ user.id }}</td>
                <td>{{ userLabel(user) }}</td>
                <td>{{ user.email }}</td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-light-danger"
                    @click="detachUser(user)"
                  >
                    Quitar
                  </button>
                </td>
              </tr>
              <tr v-if="assignedUsers.length === 0">
                <td colspan="4" class="text-muted small">
                  No hay usuarios asignados a esta empresa.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card mb-7">
        <div class="card-header">
          <h4 class="card-title fw-bold">Usuarios que reciben comisiones</h4>
          <div class="card-toolbar">
            <button
              type="button"
              class="btn btn-sm btn-primary"
              @click="openCommissionUsersModal"
            >
              + Añadir
            </button>
          </div>
        </div>

        <div class="card-body">
          <table class="table table-row-dashed table-striped table-hover table-condensed mb-0">
            <thead>
              <tr>
                <th style="width: 80px;">ID</th>
                <th>Nombre</th>
                <th>Email</th>
                <th style="width: 180px;">Comisión</th>
                <th class="text-end" style="width: 140px;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in commissionUsers"
                :key="'commission-' + row.user_id"
              >
                <td>#{{ row.user_id }}</td>
                <td>{{ userLabel(row.user) }}</td>
                <td>{{ row.user && row.user.email }}</td>
                <td>
                  <div class="input-group input-group-sm">
                    <input
                      type="text"
                      class="form-control text-end"
                      v-model="row._commissionInput"
                      inputmode="decimal"
                      autocomplete="off"
                      @input="onCommissionInput(row, $event)"
                    />
                    <span class="input-group-text">%</span>
                  </div>
                </td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-light-danger"
                    @click="confirmAndDetachCommissionUser(row)"
                  >
                    Quitar
                  </button>
                </td>
              </tr>
              <tr v-if="commissionUsers.length === 0">
                <td colspan="5" class="text-muted small">
                  No hay usuarios configurados para recibir comisiones.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Branding -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title fw-bold">Branding de la empresa</h3>

          <div class="card-toolbar">
            <div class="d-flex align-items-center gap-3">
              <div class="text-muted fs-7">Plantilla PDF</div>

              <div style="min-width: 320px;">
                <!-- Puede ser <select> simple si prefieres; uso Select2 porque está auto-registrado -->
                <select2
                  v-model="formPdfTemplate.pdf_template_id"
				  value-field="id"
				  label-field="name"
				  :search-enabled="false"
                  :options="this.pdfTemplates"
                  placeholder="Seleccione plantilla"
                  nullable="- Borrar selección -"
                />
              </div>
            </div>
          </div>
        </div>

        <div class="card-body">
          <div class="row g-5">
            <!-- Colores -->
            <div class="col-md-6">
              <label class="form-label">Color texto oscuro</label>
              <div class="input-group">
                <span class="input-group-text">#</span>
                <input
                  type="text"
                  v-model="formBranding.branding_text_dark"
                  :class="['form-control', { 'is-invalid': brandingInvalid.branding_text_dark }]"
                  :placeholder="brandingDefaults.text_dark || ''"
                  autocomplete="off"
                />
                <span
                  class="input-group-text border-start-0"
                  :style="{
                    backgroundColor: previewColor(
                      formBranding.branding_text_dark
                        || (effectiveBranding && effectiveBranding.text_dark)
                        || brandingDefaults.text_dark
                    ),
                    width: '3rem'
                  }"
                ></span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Color fondo claro</label>
              <div class="input-group">
                <span class="input-group-text">#</span>
                <input
                  type="text"
                  v-model="formBranding.branding_bg_light"
                  :class="['form-control', { 'is-invalid': brandingInvalid.branding_bg_light }]"
                  :placeholder="brandingDefaults.bg_light || ''"
                  autocomplete="off"
                />
                <span
                  class="input-group-text border-start-0"
                  :style="{
                    backgroundColor: previewColor(
                      formBranding.branding_bg_light
                        || (effectiveBranding && effectiveBranding.bg_light)
                        || brandingDefaults.bg_light
                    ),
                    width: '3rem'
                  }"
                ></span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Color texto claro</label>
              <div class="input-group">
                <span class="input-group-text">#</span>
                <input
                  type="text"
                  v-model="formBranding.branding_text_light"
                  :class="['form-control', { 'is-invalid': brandingInvalid.branding_text_light }]"
                  :placeholder="brandingDefaults.text_light || ''"
                  autocomplete="off"
                />
                <span
                  class="input-group-text border-start-0"
                  :style="{
                    backgroundColor: previewColor(
                      formBranding.branding_text_light
                        || (effectiveBranding && effectiveBranding.text_light)
                        || brandingDefaults.text_light
                    ),
                    width: '3rem'
                  }"
                ></span>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Color fondo oscuro</label>
              <div class="input-group">
                <span class="input-group-text">#</span>
                <input
                  type="text"
                  v-model="formBranding.branding_bg_dark"
                  :class="['form-control', { 'is-invalid': brandingInvalid.branding_bg_dark }]"
                  :placeholder="brandingDefaults.bg_dark || ''"
                  autocomplete="off"
                />
                <span
                  class="input-group-text border-start-0"
                  :style="{
                    backgroundColor: previewColor(
                      formBranding.branding_bg_dark
                        || (effectiveBranding && effectiveBranding.bg_dark)
                        || brandingDefaults.bg_dark
                    ),
                    width: '3rem'
                  }"
                ></span>
              </div>
            </div>

            <!-- Logo -->
            <div class="col-12 col-md-6">
              <label class="form-label d-block">Logotipo</label>

              <div class="mb-3">
                <div class="input-group">
                  <a
                    :href="logoUrl || 'javascript:void(0);'"
                    target="_blank"
                    class="input-group-text form-control bg-white link-primary"
                    :class="{ 'text-muted': !hasCustomLogo }"
                  >
                    {{ currentLogoName }}
                  </a>

                  <button
                    type="button"
                    class="btn btn-sm btn-light-primary"
                    :disabled="isUploadingLogo"
                    @click="openLogoFileDialog"
                  >
                    <span
                      v-if="isUploadingLogo"
                      class="spinner-border spinner-border-sm me-2"
                    ></span>
                    <i v-else class="bi bi-file-earmark-image"></i>
                    <span class="ms-1">Actualizar</span>
                  </button>

                  <button
                    v-if="hasCustomLogo"
                    type="button"
                    class="btn btn-sm btn-light-danger"
                    :disabled="isUploadingLogo"
                    @click="removeLogo"
                  >
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>

              <input
                :key="logoInputKey"
                ref="logoInput"
                type="file"
                class="d-none"
                accept=".png,.jpg,.jpeg"
                @change="onLogoFileSelected"
              />

              <div class="form-text">
                Formatos permitidos: PNG o JPG. Tamaño máximo 5MB.
              </div>
            </div>

            <!-- Preview -->
            <div class="col-12 col-md-6">
              <label class="form-label d-block">Preview</label>
			  <img v-if="logoUrl" :src="logoUrl" style="max-width: 50%; height: auto; max-height: 80px;" class="m-2" />
              <div
                v-if="effectiveBranding"
                class="border rounded p-4"
                :style="{
                  backgroundColor: effectiveBranding.bg_dark,
                  color: effectiveBranding.text_light,
                }"
              >
                <div class="fw-bold mb-2">
                  {{ company ? company.name : 'Nombre de empresa' }}
                </div>
                <div
                  class="rounded px-3 py-2"
                  :style="{
                    backgroundColor: effectiveBranding.bg_light,
                    color: effectiveBranding.text_dark,
                  }"
                >
                  Ejemplo de texto sobre fondo claro
                </div>
              </div>
              <div v-else class="text-muted">
                No hay datos de branding todavía.
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de usuarios (operadores) -->
    <div
      class="modal fade"
      tabindex="-1"
      ref="usersModal"
    >
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Usuarios que pueden operar en la empresa</h5>
            <button
              type="button"
              class="btn-close"
              aria-label="Close"
              @click="closeUsersModal"
            ></button>
          </div>

          <div class="modal-body">
            <div class="d-flex align-items-center mb-4">
              <div class="flex-grow-1">
                <input
                  v-model="usersSearch"
                  type="text"
                  class="form-control"
                  placeholder="Buscar por nombre o email"
                  @input="onUsersSearchInput"
                />
              </div>
            </div>

            <div v-if="isLoadingUsers" class="text-center py-6">
              <div class="spinner-border" role="status">
                <span class="visually-hidden">Cargando usuarios...</span>
              </div>
            </div>

            <div v-else>
              <div v-if="usersList.length === 0" class="text-muted">
                No se encontraron usuarios con los filtros actuales.
              </div>
              <div v-else>
                <table class="table table-row-dashed table-striped table-hover table-condensed">
                  <thead>
                    <tr>
                      <th style="width: 80px;">ID</th>
                      <th>Nombre</th>
                      <th>Email</th>
                      <th class="text-end" style="width: 140px;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="user in usersList"
                      :key="'modal-user-' + user.id"
                    >
                      <td>#{{ user.id }}</td>
                      <td>{{ user.display_name }}</td>
                      <td>{{ user.email }}</td>
                      <td class="text-end">
                        <button
                          v-if="isUserAttached(user.id)"
                          type="button"
                          class="btn btn-sm btn-light-danger"
                          @click="detachUser(user)"
                        >
                          Quitar
                        </button>
                        <button
                          v-else
                          type="button"
                          class="btn btn-sm btn-light-primary"
                          @click="attachUser(user)"
                        >
                          Añadir
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <div
                  v-if="usersPagination.total > 0"
                  class="d-flex justify-content-between align-items-center mt-4"
                >
                  <div class="text-muted fs-7">
                    Mostrando
                    {{ usersFrom }}
                    –
                    {{ usersTo }}
                    de
                    {{ usersPagination.total }}
                    usuarios
                  </div>
                  <div class="btn-group">
                    <button
                      type="button"
                      class="btn btn-sm btn-light"
                      :disabled="usersPagination.current_page <= 1"
                      @click="goToUsersPage(usersPagination.current_page - 1)"
                    >
                      Anterior
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-light"
                      :disabled="usersPagination.current_page >= usersPagination.last_page"
                      @click="goToUsersPage(usersPagination.current_page + 1)"
                    >
                      Siguiente
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-light"
              @click="closeUsersModal"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal usuarios de comisiones -->
    <div
      class="modal fade"
      tabindex="-1"
      ref="commissionUsersModal"
    >
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Usuarios que reciben comisiones</h5>
            <button
              type="button"
              class="btn-close"
              aria-label="Close"
              @click="closeCommissionUsersModal"
            ></button>
          </div>

          <div class="modal-body">
            <div class="d-flex align-items-center mb-4">
              <div class="flex-grow-1">
                <input
                  v-model="commissionUsersSearch"
                  type="text"
                  class="form-control"
                  placeholder="Buscar por nombre o email"
                  @input="onCommissionUsersSearchInput"
                />
              </div>
            </div>

            <div v-if="isLoadingCommissionUsers" class="text-center py-6">
              <div class="spinner-border" role="status">
                <span class="visually-hidden">Cargando usuarios...</span>
              </div>
            </div>

            <div v-else>
              <div v-if="commissionUsersList.length === 0" class="text-muted">
                No se encontraron usuarios con los filtros actuales.
              </div>
              <div v-else>
                <table class="table table-row-dashed table-striped table-hover table-condensed">
                  <thead>
                    <tr>
                      <th style="width: 80px;">ID</th>
                      <th>Nombre</th>
                      <th>Email</th>
                      <th class="text-end" style="width: 140px;">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="user in commissionUsersList"
                      :key="'commission-modal-user-' + user.id"
                    >
                      <td>#{{ user.id }}</td>
                      <td>{{ user.display_name }}</td>
                      <td>{{ user.email }}</td>
                      <td class="text-end">
                        <button
                          v-if="isUserInCommissionList(user.id)"
                          type="button"
                          class="btn btn-sm btn-light-danger"
                          @click="detachCommissionUser(user)"
                        >
                          Quitar
                        </button>
                        <button
                          v-else
                          type="button"
                          class="btn btn-sm btn-light-primary"
                          @click="attachCommissionUser(user)"
                        >
                          Añadir
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>

                <div
                  v-if="commissionUsersPagination.total > 0"
                  class="d-flex justify-content-between align-items-center mt-4"
                >
                  <div class="text-muted fs-7">
                    Mostrando
                    {{ commissionUsersFrom }}
                    –
                    {{ commissionUsersTo }}
                    de
                    {{ commissionUsersPagination.total }}
                    usuarios
                  </div>
                  <div class="btn-group">
                    <button
                      type="button"
                      class="btn btn-sm btn-light"
                      :disabled="commissionUsersPagination.current_page <= 1"
                      @click="goToCommissionUsersPage(commissionUsersPagination.current_page - 1)"
                    >
                      Anterior
                    </button>
                    <button
                      type="button"
                      class="btn btn-sm btn-light"
                      :disabled="commissionUsersPagination.current_page >= commissionUsersPagination.last_page"
                      @click="goToCommissionUsersPage(commissionUsersPagination.current_page + 1)"
                    >
                      Siguiente
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-light"
              @click="closeCommissionUsersModal"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import * as format from '@/utils/format'

export default {
  name: 'AdminCompaniesEdit',

  props: {
    companyId: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      isLoading: true,

      autosaveDelay:
        (window.__RUNTIME_CONFIG__ &&
          window.__RUNTIME_CONFIG__.autosaveDelayMs) ||
        800,
      autosaveTimers: {},
      isReadyForAutosave: false,

      company: null,

      // Datos básicos
      formBasic: {
        name: '',
        short_code: '',
        phone: '',
        email: '',
        description: '',
        status: 'active',
      },

      // Plantilla PDF (Company.pdf_template_id)
      pdfTemplates: [],
      formPdfTemplate: {
        pdf_template_id: null,
      },
      isSavingPdfTemplate: false,

      // Estado para short_code
      shortCodeChecking: false,
      shortCodeStatus: null, // null | 'available' | 'taken'
      shortCodeMessage: null,
      shortCodeTimeoutId: null,

      // Usuarios asignados
      assignedUsers: [],

      // Usuarios de comisiones
      commissionUsers: [],

      // Branding
      brandingDefaults: {
        text_dark: null,
        bg_light: null,
        text_light: null,
        bg_dark: null,
        logo: null,
      },
      formBranding: {
        branding_text_dark: '',
        branding_bg_light: '',
        branding_text_light: '',
        branding_bg_dark: '',
      },
      brandingInvalid: {
        branding_text_dark: false,
        branding_bg_light: false,
        branding_text_light: false,
        branding_bg_dark: false,
      },
      isUploadingLogo: false,
      logoInputKey: 1,

      // Modal usuarios asignados
      usersModalInstance: null,
      usersSearch: '',
      usersSearchTimer: null,
      usersList: [],
      usersPagination: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
      },
      isLoadingUsers: false,

      // Modal usuarios de comisiones
      commissionUsersModalInstance: null,
      commissionUsersSearch: '',
      commissionUsersSearchTimer: null,
      commissionUsersList: [],
      commissionUsersPagination: {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
      },
      isLoadingCommissionUsers: false,
    }
  },

  computed: {
    effectiveBranding() {
      if (!this.company || !this.company.branding) return null
      return this.company.branding
    },

    hasCustomLogo() {
      return !!(this.company && this.company.branding_logo_file_id)
    },

    currentLogoName() {
      if (
        this.effectiveBranding &&
        this.effectiveBranding.logo &&
        this.effectiveBranding.logo.original_name
      ) {
        return this.effectiveBranding.logo.original_name
      }
      if (this.brandingDefaults.logo && this.brandingDefaults.logo.original_name) {
        return this.brandingDefaults.logo.original_name
      }
      return 'No hay archivo seleccionado'
    },

    logoUrl() {
      if (
        this.effectiveBranding &&
        this.effectiveBranding.logo &&
        this.effectiveBranding.logo.url
      ) {
        return this.effectiveBranding.logo.url
      }
      if (this.brandingDefaults.logo && this.brandingDefaults.logo.url) {
        return this.brandingDefaults.logo.url
      }
      return null
    },

    // Paginación modal usuarios asignados
    usersFrom() {
      if (this.usersPagination.total === 0) return 0
      return (
        (this.usersPagination.current_page - 1) *
          this.usersPagination.per_page +
        1
      )
    },

    usersTo() {
      if (this.usersPagination.total === 0) return 0
      const tentative =
        this.usersPagination.current_page * this.usersPagination.per_page
      return tentative > this.usersPagination.total
        ? this.usersPagination.total
        : tentative
    },

    // Paginación modal usuarios de comisiones
    commissionUsersFrom() {
      if (this.commissionUsersPagination.total === 0) return 0
      return (
        (this.commissionUsersPagination.current_page - 1) *
          this.commissionUsersPagination.per_page +
        1
      )
    },

    commissionUsersTo() {
      if (this.commissionUsersPagination.total === 0) return 0
      const tentative =
        this.commissionUsersPagination.current_page *
        this.commissionUsersPagination.per_page
      return tentative > this.commissionUsersPagination.total
        ? this.commissionUsersPagination.total
        : tentative
    },
  },

  watch: {
    // Datos básicos
    'formBasic.name'() {
      this.scheduleAutosaveSection('basic')
    },
    'formBasic.short_code'(newValue) {
      this.handleShortCodeChange(newValue)
      this.scheduleAutosaveSection('basic')
    },
    'formBasic.phone'() {
      this.scheduleAutosaveSection('basic')
    },
    'formBasic.email'() {
      this.scheduleAutosaveSection('basic')
    },
    'formBasic.description'() {
      this.scheduleAutosaveSection('basic')
    },
    'formBasic.status'() {
      this.scheduleAutosaveSection('basic')
    },

    // Branding
    'formBranding.branding_text_dark'(value) {
      this.onBrandingFieldChanged('branding_text_dark', value)
    },
    'formBranding.branding_bg_light'(value) {
      this.onBrandingFieldChanged('branding_bg_light', value)
    },
    'formBranding.branding_text_light'(value) {
      this.onBrandingFieldChanged('branding_text_light', value)
    },
    'formBranding.branding_bg_dark'(value) {
      this.onBrandingFieldChanged('branding_bg_dark', value)
    },

    // Plantilla PDF: guardar apenas cambie
    'formPdfTemplate.pdf_template_id'(value) {
      if (!this.isReadyForAutosave) return
      this.savePdfTemplate(value)
    },
  },

  mounted() {
    this.loadAll()
  },

  methods: {
    async loadAll() {
      this.isLoading = true

      try {
        const companyUrl = this.route('admin.companies.show', {
          company: this.companyId,
        })
        const commissionUrl = this.route(
          'admin.companies.commission-users.index',
          { company: this.companyId },
        )

        const [companyResp, commissionResp] = await Promise.all([
          axios.get(companyUrl),
          axios.get(commissionUrl),
        ])

        const companyPayload = companyResp.data || {}
        const company = companyPayload.data || companyPayload

        const commissionPayload = commissionResp.data || {}
        const commissionList = Array.isArray(commissionPayload.data)
          ? commissionPayload.data
          : []

        this.isReadyForAutosave = false

        this.company = company
        this.assignedUsers = companyPayload.assigned_users || []
        this.brandingDefaults =
          companyPayload.branding_defaults || this.brandingDefaults
        this.commissionUsers = this.normalizeCommissionUsers(commissionList)

        this.pdfTemplates = Array.isArray(companyPayload.pdf_templates)
          ? companyPayload.pdf_templates
          : []
		  
        this.fillFormsFromCompany(company)

        this.$nextTick(() => {
          this.isReadyForAutosave = true
        })
      } catch (error) {
        this.flashError(
          error,
          'No se pudo cargar la empresa.',
        )
      } finally {
        this.isLoading = false
      }
    },

    fillFormsFromCompany(company) {
      if (!company) {
        this.formBasic.name = ''
        this.formBasic.short_code = ''
        this.formBasic.phone = ''
        this.formBasic.email = ''
        this.formBasic.description = ''
        this.formBasic.status = 'active'
        this.formBranding.branding_text_dark = ''
        this.formBranding.branding_bg_light = ''
        this.formBranding.branding_text_light = ''
        this.formBranding.branding_bg_dark = ''
        this.formPdfTemplate.pdf_template_id = null
      } else {
        this.formBasic.name = company.name || ''
        this.formBasic.short_code = company.short_code || ''
        this.formBasic.phone = company.phone || ''
        this.formBasic.email = company.email || ''
        this.formBasic.description = company.description || ''
        this.formBasic.status = company.status || 'active'

        const branding = company.branding || {}

        this.formBranding.branding_text_dark =
          branding.custom_text_dark || ''
        this.formBranding.branding_bg_light =
          branding.custom_bg_light || ''
        this.formBranding.branding_text_light =
          branding.custom_text_light || ''
        this.formBranding.branding_bg_dark =
          branding.custom_bg_dark || ''

        this.formPdfTemplate.pdf_template_id =
          company.pdf_template_id != null ? company.pdf_template_id : null
      }

      Object.keys(this.brandingInvalid).forEach((key) => {
        this.brandingInvalid[key] = false
      })

      // Reset estado de short_code
      this.shortCodeStatus = null
      this.shortCodeMessage = null
      this.shortCodeChecking = false
      if (this.shortCodeTimeoutId) {
        clearTimeout(this.shortCodeTimeoutId)
        this.shortCodeTimeoutId = null
      }
    },

    statusBadgeClass(status) {
      if (status === 'active') return 'badge-light-success'
      if (status === 'inactive') return 'badge-light-warning'
      if (status === 'archived') return 'badge-light-secondary'
      return 'badge-light'
    },

    previewColor(value) {
      if (!value) return 'transparent'

      let hex = String(value).trim().replace(/^#/, '')

      if (hex.length === 3) {
        hex = hex
          .split('')
          .map((c) => c + c)
          .join('')
      }

      if (!/^[0-9A-Fa-f]{6}$/.test(hex)) {
        return 'transparent'
      }

      return `#${hex}`
    },

    isValidHexColor(value) {
      if (!value || value === '') return true

      const hex = String(value).trim().replace(/^#/, '')

      return /^[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/.test(hex)
    },

    onBrandingFieldChanged(field, value) {
      const isValid = this.isValidHexColor(value)
      this.brandingInvalid[field] = !isValid

      if (isValid) {
        this.scheduleAutosaveSection('branding')
      }
    },

    scheduleAutosaveSection(section) {
      if (!this.isReadyForAutosave) return

      if (!this.autosaveTimers) {
        this.autosaveTimers = {}
      }

      const key = `section:${section}`

      if (this.autosaveTimers[key]) {
        clearTimeout(this.autosaveTimers[key])
      }

      this.autosaveTimers[key] = setTimeout(() => {
        this.autosaveTimers[key] = null
        if (section === 'basic') {
          this.saveBasic()
        } else if (section === 'branding') {
          this.saveBranding()
        }
      }, this.autosaveDelay)
    },

    async saveBasic() {
      if (!this.company) return

      try {
        const payload = {
          phone: this.formBasic.phone || null,
          email: this.formBasic.email || null,
          description: this.formBasic.description || null,
          status: this.formBasic.status || 'active',
        }

        const name = (this.formBasic.name || '').trim()
        if (name !== '') {
          payload.name = name
        }

        const shortCodeRaw = (this.formBasic.short_code || '').trim()
        if (shortCodeRaw !== '') {
          payload.short_code = shortCodeRaw.toUpperCase()
        }

        await this.submitUpdate(payload, 'basic')
      } catch (error) {
        this.flashError(
          error,
          'No se pudieron guardar los datos básicos.',
        )
      }
    },

    // Guardado inmediato plantilla PDF
    async savePdfTemplate(value) {
      if (!this.company) return

      this.isSavingPdfTemplate = true
      try {
        const payload = {
          pdf_template_id: value || null,
        }
        await this.submitUpdate(payload, 'pdf_template')
      } catch (error) {
        this.flashError(error, 'No se pudo guardar la plantilla PDF.')
      } finally {
        this.isSavingPdfTemplate = false
      }
    },

    // Validación y check remoto del short_code
    handleShortCodeChange(value) {
      this.shortCodeStatus = null
      this.shortCodeMessage = null

      if (this.shortCodeTimeoutId) {
        clearTimeout(this.shortCodeTimeoutId)
        this.shortCodeTimeoutId = null
      }

      const trimmed = (value || '').trim()

      if (!trimmed) {
        return
      }

      if (trimmed.length < 3) {
        this.shortCodeMessage = 'El código debe tener al menos 3 caracteres.'
        return
      }

      this.shortCodeChecking = true

      const delay = this.autosaveDelay || 700

      this.shortCodeTimeoutId = setTimeout(() => {
        this.checkShortCodeAvailability(trimmed)
      }, delay)
    },

    async checkShortCodeAvailability(value) {
      try {
        const params = {
          short_code: value,
        }

        const currentId = this.company ? this.company.id : this.companyId
        if (currentId) {
          // Enviamos ambos por compatibilidad con el backend actual
          params.company_id = currentId
          params.ignore_id = currentId
        }

        const url = this.route('admin.companies.check-short-code', params)
        const { data } = await axios.get(url)

        if (data.is_available) {
          this.shortCodeStatus = 'available'
          this.shortCodeMessage = null
        } else if (data.reason === 'too_short') {
          this.shortCodeStatus = null
          this.shortCodeMessage = 'El código es demasiado corto.'
        } else if (data.reason === 'empty') {
          this.shortCodeStatus = null
          this.shortCodeMessage = 'El código no puede estar vacío.'
        } else {
          this.shortCodeStatus = 'taken'
          this.shortCodeMessage = 'El código ya está en uso.'
        }
      } catch (e) {
        // eslint-disable-next-line no-console
        console.error(e)
        this.shortCodeStatus = null
        this.shortCodeMessage = 'No se pudo verificar el código.'
      } finally {
        this.shortCodeChecking = false
      }
    },

    async saveBranding() {
      if (!this.company) return

      const invalid = Object.values(this.brandingInvalid).some(Boolean)
      if (invalid) return

      try {
        const payload = {
          branding_text_dark: this.formBranding.branding_text_dark || null,
          branding_bg_light: this.formBranding.branding_bg_light || null,
          branding_text_light: this.formBranding.branding_text_light || null,
          branding_bg_dark: this.formBranding.branding_bg_dark || null,
        }

        await this.submitUpdate(payload, 'branding')
      } catch (error) {
        this.flashError(
          error,
          'No se pudo guardar el branding.',
        )
      }
    },

    async submitUpdate(payload, section = null) {
      const url = this.route('admin.companies.update', {
        company: this.companyId,
      })

      const { data } = await axios.put(url, payload, {
        headers: {
          'Content-Type': 'application/json',
        },
      })

      const response = data || {}
      const company = response.data || response

      this.isReadyForAutosave = false

      this.company = company
      this.fillFormsFromCompany(company)
      this.assignedUsers = response.assigned_users || this.assignedUsers

      this.$nextTick(() => {
        this.isReadyForAutosave = true
      })

      let message = 'Empresa actualizada.'

      if (section === 'basic') {
        message = 'Datos básicos actualizados.'
      } else if (section === 'branding') {
        message = 'Branding actualizado.'
      } else if (section === 'pdf_template') {
        message = 'Plantilla PDF actualizada.'
      }

      if (typeof window !== 'undefined' && typeof window.flash === 'function') {
        window.flash(message, 'success')
      }
    },

    // Logo
    openLogoFileDialog() {
      if (!this.$refs.logoInput || this.isUploadingLogo) return
      this.$refs.logoInput.click()
    },

    async onLogoFileSelected(event) {
      const file =
        event.target.files && event.target.files[0]
          ? event.target.files[0]
          : null
      if (!file) return

      this.isUploadingLogo = true

      const url = this.route('admin.companies.update', {
        company: this.companyId,
      })

      const formData = new FormData()
      formData.append('_method', 'PUT')
      formData.append('branding_logo', file)

      try {
        const { data } = await axios.post(url, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })

        const response = data || {}
        const company = response.data || response

        this.isReadyForAutosave = false

        this.company = company
        this.fillFormsFromCompany(company)

        this.$nextTick(() => {
          this.isReadyForAutosave = true
        })

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash('Logo actualizado correctamente.', 'success')
        }
      } catch (error) {
        this.flashError(
          error,
          'No se pudo actualizar el logo.',
        )
      } finally {
        this.isUploadingLogo = false
        this.logoInputKey += 1
      }
    },

    async removeLogo() {
      if (this.isUploadingLogo) return

      if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
        const confirmed = window.confirm(
          '¿Seguro que deseas quitar el logotipo de la empresa?',
        )
        if (!confirmed) {
          return
        }
      }

      this.isUploadingLogo = true

      const url = this.route('admin.companies.update', {
        company: this.companyId,
      })

      const formData = new FormData()
      formData.append('_method', 'PUT')
      formData.append('branding_logo_remove', '1')

      try {
        const { data } = await axios.post(url, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })

        const response = data || {}
        const company = response.data || response

        this.isReadyForAutosave = false

        this.company = company
        this.fillFormsFromCompany(company)

        this.$nextTick(() => {
          this.isReadyForAutosave = true
        })

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash('Logo eliminado correctamente.', 'success')
        }
      } catch (error) {
        this.flashError(
          error,
          'No se pudo eliminar el logo.',
        )
      } finally {
        this.isUploadingLogo = false
      }
    },

    // Modal de usuarios asignados
    initUsersModal() {
      if (typeof window === 'undefined' || !window.bootstrap) return

      const el = this.$refs.usersModal
      if (!el) return

      this.usersModalInstance =
        window.bootstrap.Modal.getOrCreateInstance(el)
    },

    openUsersModal() {
      if (!this.usersModalInstance) {
        this.initUsersModal()
      }
      if (!this.usersModalInstance) return

      this.usersSearch = ''
      this.fetchUsersPage(1)
      this.usersModalInstance.show()
    },

    closeUsersModal() {
      if (this.usersModalInstance) {
        this.usersModalInstance.hide()
      }
    },

    onUsersSearchInput() {
      if (this.usersSearchTimer) {
        clearTimeout(this.usersSearchTimer)
      }

      this.usersSearchTimer = setTimeout(() => {
        this.fetchUsersPage(1)
      }, this.autosaveDelay)
    },

    async fetchUsersPage(page = 1) {
      if (!this.company) return

      this.isLoadingUsers = true

      try {
        const url = this.route('admin.companies.users.search', {
          company: this.companyId,
        })

        const { data } = await axios.get(url, {
          params: {
            page,
            per_page: this.usersPagination.per_page,
            search: this.usersSearch,
          },
        })

        this.usersList = data.data || []
        this.usersPagination = {
          current_page: data.meta.current_page,
          last_page: data.meta.last_page,
          per_page: data.meta.per_page,
          total: data.meta.total,
        }
      } catch (error) {
        this.flashError(
          error,
          'No se pudieron cargar los usuarios disponibles para esta empresa.',
        )
      } finally {
        this.isLoadingUsers = false
      }
    },

    goToUsersPage(page) {
      if (
        page < 1 ||
        page > (this.usersPagination.last_page || 1)
      ) {
        return
      }
      this.fetchUsersPage(page)
    },

    isUserAttached(userId) {
      if (!this.company || !Array.isArray(this.company.users_ids)) return false
      return this.company.users_ids.includes(userId)
    },

    async attachUser(user) {
      if (!this.company || !user) return

      try {
        const url = this.route('admin.companies.users.attach', {
          company: this.companyId,
          user: user.id,
        })

        const { data } = await axios.post(url)

        const response = data || {}
        const company = response.data || response

        this.isReadyForAutosave = false

        this.company = company
        this.fillFormsFromCompany(company)

        this.assignedUsers = this.mergeAssignedUsers(
          this.assignedUsers,
          [user],
          company.users_ids,
        )

        this.$nextTick(() => {
          this.isReadyForAutosave = true
        })

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash('Usuario añadido a la empresa.', 'success')
        }
      } catch (error) {
        this.flashError(
          error,
          'No se pudo añadir el usuario a la empresa.',
        )
      }
    },

    async detachUser(user) {
      if (!this.company || !user) return

      try {
        const url = this.route('admin.companies.users.detach', {
          company: this.companyId,
          user: user.id,
        })

        const { data } = await axios.delete(url)

        const response = data || {}
        const company = response.data || response

        this.isReadyForAutosave = false

        this.company = company
        this.fillFormsFromCompany(company)

        this.assignedUsers = this.assignedUsers.filter(
          (u) => u.id !== user.id,
        )

        this.$nextTick(() => {
          this.isReadyForAutosave = true
        })

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          window.flash('Usuario quitado de la empresa.', 'success')
        }
      } catch (error) {
        this.flashError(
          error,
          'No se pudo quitar el usuario de la empresa.',
        )
      }
    },

    mergeAssignedUsers(current, newOnes, usersIdsFromCompany) {
      const map = new Map()

      ;(current || []).forEach((u) => {
        map.set(u.id, u)
      })
      ;(newOnes || []).forEach((u) => {
        map.set(u.id, u)
      })

      const ids = Array.isArray(usersIdsFromCompany)
        ? usersIdsFromCompany
        : Array.from(map.keys())

      return ids
        .map((id) => map.get(id))
        .filter((u) => !!u)
    },

    // -------------------------------
    // Usuarios de comisiones
    // -------------------------------
    normalizeCommissionUsers(list) {
      if (!Array.isArray(list)) return []

      return list.map((item) => {
        const user = item.user || {
          id: item.user_id,
          email: item.user_email || '',
          display_name: item.user_display_name || '',
        }

        let numeric = null
        if (item.commission != null) {
          const n = Number(item.commission)
          if (!Number.isNaN(n)) {
            numeric = Math.max(0, Math.min(100, n))
          }
        }

        const display =
          numeric !== null ? format.formatDecimal(numeric) : ''

        return {
          id: item.id,
          user_id: user.id,
          user,
          commission: numeric,
          _commissionInput: display,
          _lastValidCommissionInput: display,
        }
      })
    },

    onCommissionInput(row, event) {
      if (!row) return

      const raw =
        (event && event.target ? event.target.value : row._commissionInput) ||
        ''

      const { normalized, display } =
        format.normalizeDecimalDigitsInput(raw)

      let numeric = null
      if (normalized !== null && normalized !== '' && normalized !== undefined) {
        const n = Number(normalized)
        if (!Number.isNaN(n)) {
          numeric = n
        }
      }

      if (numeric !== null && numeric > 100) {
        const prev = row._lastValidCommissionInput || ''
        row._commissionInput = prev
        if (event && event.target) {
          event.target.value = prev
        }
        return
      }

      row._commissionInput = display
      row._lastValidCommissionInput = display
      row.commission = numeric

      this.scheduleCommissionAutosave(row)
    },

    scheduleCommissionAutosave(row) {
      if (!this.isReadyForAutosave || !row || !row.id) return

      if (!this.autosaveTimers) {
        this.autosaveTimers = {}
      }

      const key = `commission:${row.id}`

      if (this.autosaveTimers[key]) {
        clearTimeout(this.autosaveTimers[key])
      }

      this.autosaveTimers[key] = setTimeout(() => {
        this.autosaveTimers[key] = null
        this.saveCommission(row)
      }, this.autosaveDelay)
    },

    async saveCommission(row) {
      if (!this.company || !row || !row.id) return

      const value =
        typeof row.commission === 'number' ? row.commission : 0

      try {
        const url = this.route(
          'admin.companies.commission-users.update',
          {
            company: this.companyId,
            commissionUser: row.id,
          },
        )

        const { data } = await axios.patch(
          url,
          { commission: value },
          {
            headers: { 'Content-Type': 'application/json' },
          },
        )

        const payload = data || {}
        const updated = payload.data || payload

        if (updated && typeof updated.commission !== 'undefined') {
          const n = Number(updated.commission)
          const numeric = Number.isNaN(n)
            ? null
            : Math.max(0, Math.min(100, n))
          const display =
            numeric !== null ? format.formatDecimal(numeric) : ''

          row.commission = numeric
          row._commissionInput = display
          row._lastValidCommissionInput = display
        }

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          const toast = payload.toast
          const msg = toast?.message || 'Comisión actualizada.'
          const type = toast?.type || 'success'
          window.flash(msg, type)
        }
      } catch (error) {
        this.flashError(
          error,
          'No se pudo actualizar la comisión para este usuario.',
        )
      }
    },

    // Modal usuarios de comisiones
    initCommissionUsersModal() {
      if (typeof window === 'undefined' || !window.bootstrap) return

      const el = this.$refs.commissionUsersModal
      if (!el) return

      this.commissionUsersModalInstance =
        window.bootstrap.Modal.getOrCreateInstance(el)
    },

    openCommissionUsersModal() {
      if (!this.commissionUsersModalInstance) {
        this.initCommissionUsersModal()
      }
      if (!this.commissionUsersModalInstance) return

      this.commissionUsersSearch = ''
      this.fetchCommissionUsersPage(1)
      this.commissionUsersModalInstance.show()
    },

    closeCommissionUsersModal() {
      if (this.commissionUsersModalInstance) {
        this.commissionUsersModalInstance.hide()
      }
    },

    onCommissionUsersSearchInput() {
      if (this.commissionUsersSearchTimer) {
        clearTimeout(this.commissionUsersSearchTimer)
      }

      this.commissionUsersSearchTimer = setTimeout(() => {
        this.fetchCommissionUsersPage(1)
      }, this.autosaveDelay)
    },

    async fetchCommissionUsersPage(page = 1) {
      if (!this.company) return

      this.isLoadingCommissionUsers = true

      try {
        const url = this.route(
          'admin.companies.commission-users.available',
          { company: this.companyId },
        )

        const { data } = await axios.get(url, {
          params: {
            page,
            per_page: this.commissionUsersPagination.per_page,
            q: this.commissionUsersSearch,
          },
        })

        const payload = data || {}

        this.commissionUsersList = payload.data || []
        this.commissionUsersPagination = {
          current_page: payload.meta.current_page,
          last_page: payload.meta.last_page,
          per_page: payload.meta.per_page,
          total: payload.meta.total,
        }
      } catch (error) {
        this.flashError(
          error,
          'No se pudieron cargar los usuarios disponibles para comisiones.',
        )
      } finally {
        this.isLoadingCommissionUsers = false
      }
    },

    goToCommissionUsersPage(page) {
      if (
        page < 1 ||
        page > (this.commissionUsersPagination.last_page || 1)
      ) {
        return
      }
      this.fetchCommissionUsersPage(page)
    },

    isUserInCommissionList(userId) {
      if (!Array.isArray(this.commissionUsers)) return false
      return this.commissionUsers.some((row) => row.user_id === userId)
    },

    async attachCommissionUser(user) {
      if (!this.company || !user) return

      try {
        const url = this.route(
          'admin.companies.commission-users.store',
          { company: this.companyId },
        )

        const { data } = await axios.post(url, {
          user_id: user.id,
        })

        const payload = data || {}
        const rowData = payload.data || payload
        const normalized = this.normalizeCommissionUsers([rowData])[0]

        if (normalized) {
          this.commissionUsers.push(normalized)
        }

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          const toast = payload.toast
          const msg =
            toast?.message ||
            'Usuario añadido a la lista de comisiones.'
          const type = toast?.type || 'success'
          window.flash(msg, type)
        }
      } catch (error) {
        this.flashError(
          error,
          'No se pudo añadir el usuario a la lista de comisiones.',
        )
      }
    },

    async detachCommissionUser(payload) {
      if (!this.company || !payload) return

      let target = null

      if (payload.id && payload.user_id) {
        target = payload
      } else if (payload.user_id) {
        target =
          this.commissionUsers.find(
            (row) => row.user_id === payload.user_id,
          ) || null
      } else if (payload.id) {
        target =
          this.commissionUsers.find(
            (row) => row.user_id === payload.id,
          ) || null
      }

      if (!target) return

      try {
        const url = this.route(
          'admin.companies.commission-users.destroy',
          {
            company: this.companyId,
            commissionUser: target.id,
          },
        )

        const { data } = await axios.delete(url)

        this.commissionUsers = this.commissionUsers.filter(
          (row) => row.id !== target.id,
        )

        if (typeof window !== 'undefined' && typeof window.flash === 'function') {
          const toast = data?.toast
          const msg =
            toast?.message ||
            'Usuario eliminado de la lista de comisiones.'
          const type = toast?.type || 'success'
          window.flash(msg, type)
        }
      } catch (error) {
        this.flashError(
          error,
          'No se pudo quitar el usuario de la lista de comisiones.',
        )
      }
    },

    async confirmAndDetachCommissionUser(row) {
      if (!row || !row.user) return

      const confirmed = window.confirm(
        '¿Seguro que deseas quitar este usuario de la lista de comisiones?',
      )
      if (!confirmed) return

      await this.detachCommissionUser(row)
    },

    // -------------------------------
    // Helpers comunes
    // -------------------------------
    userLabel(user) {
      if (!user) return ''
      return user.display_name || ''
    },

    flashError(error, fallbackMessage) {
      // eslint-disable-next-line no-console
      console.error(error)

      const message =
        error?.response?.data?.message ||
        fallbackMessage ||
        'Ocurrió un error inesperado.'

      if (typeof window !== 'undefined' && typeof window.flash === 'function') {
        window.flash(message, 'danger')
      }
    },
  },
}
</script>
