<template>
  <div v-if="isOpen">
    <div
      class="modal fade show d-block"
      tabindex="-1"
      role="dialog"
      style="z-index: 1050;"
    >
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">
              <span v-if="mode === 'create'">
                Crear variable de configuración
              </span>
              <span v-else>
                Editar definición: {{ form.name || '(sin nombre)' }}
              </span>
            </h5>
            <button type="button" class="btn-close" @click="close"></button>
          </div>

          <!-- Body -->
          <div class="modal-body" v-if="loaded">
            <div class="mb-3">
              <label class="form-label">Categoría</label>
              <select
                v-model="form.category"
                class="form-select form-select-sm"
              >
                <option value="">-- Seleccionar categoría --</option>
                <option
                  v-for="(label, key) in categories"
                  :key="key"
                  :value="key"
                >
                  {{ label }} ({{ key }})
                </option>
              </select>
              <div v-if="errors.category" class="text-danger small">
                {{ errors.category }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Nombre visible</label>
              <input
                type="text"
                class="form-control form-control-sm"
                v-model="form.name"
              />
              <div v-if="errors.name" class="text-danger small">
                {{ errors.name }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Token</label>
              <input
                type="text"
                class="form-control form-control-sm"
                v-model="form.token"
              />
              <div class="form-text">
                Identificador dentro de la categoría (category + token únicos).
              </div>
              <div v-if="errors.token" class="text-danger small">
                {{ errors.token }}
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Tipo de valor</label>
              <select
                v-model="form.type"
                class="form-select form-select-sm"
              >
                <option
                  v-for="t in availableTypes"
                  :key="t.value"
                  :value="t.value"
                >
                  {{ t.label }} ({{ t.value }})
                </option>
              </select>
              <div class="form-text">
                Cambiar el tipo puede dejar el valor actual en un estado inconsistente.
              </div>
              <div v-if="errors.type" class="text-danger small">
                {{ errors.type }}
              </div>
            </div>

            <!-- ENUM -->
            <div v-if="isEnumType" class="mb-4">
              <label class="form-label">Opciones del enum</label>
              <div class="table-responsive border rounded">
                <table class="table table-sm mb-0">
                  <thead class="table-light">
                    <tr>
                      <th style="width: 40%;">Key</th>
                      <th style="width: 50%;">Etiqueta</th>
                      <th style="width: 10%;" class="text-end">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(opt, idx) in form.enumOptions" :key="idx">
                      <td>
                        <input
                          type="text"
                          class="form-control form-control-sm"
                          v-model="opt.key"
                        />
                      </td>
                      <td>
                        <input
                          type="text"
                          class="form-control form-control-sm"
                          v-model="opt.label"
                        />
                      </td>
                      <td class="text-end">
                        <button
                          type="button"
                          class="btn btn-xs btn-light-danger"
                          @click="removeEnumOption(idx)"
                        >
                          ×
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <button
                type="button"
                class="btn btn-xs btn-light-primary mt-2"
                @click="addEnumOption"
              >
                + Añadir opción
              </button>
            </div>

            <!-- FILE CONFIG -->
            <div v-if="isFileType" class="mb-3">
              <label class="form-label">Configuración de archivo</label>
              <div class="row g-2">
                <div class="col-md-6">
                  <label class="form-label small mb-1">Extensiones permitidas</label>
                  <input
                    type="text"
                    class="form-control form-control-sm"
                    v-model="form.fileAllowedExtensions"
                    placeholder="ej: pdf,jpg,png"
                  />
                </div>
                <div class="col-md-6">
                  <label class="form-label small mb-1">Tamaño máx. (KB)</label>
                  <input
                    type="number"
                    class="form-control form-control-sm"
                    v-model.number="form.fileMaxSizeKb"
                    placeholder="ej: 2048"
                  />
                </div>
              </div>
            </div>

            <div v-if="errors.__general" class="alert alert-danger py-2">
              {{ errors.__general }}
            </div>
          </div>

          <div class="modal-body" v-else>
            <div class="text-center py-5">
              <div class="spinner-border spinner-border-sm" role="status"></div>
              <span class="ms-2 small text-muted">Cargando variable...</span>
            </div>
          </div>

          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-light btn-sm"
              :disabled="isSaving"
              @click="close"
            >
              Cerrar
            </button>
            <button
              type="button"
              class="btn btn-primary btn-sm"
              :disabled="isSaving"
              @click="submit"
            >
              <span v-if="isSaving" class="spinner-border spinner-border-sm me-1"></span>
              <span v-if="mode === 'create'">Crear variable</span>
              <span v-else>Guardar cambios</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Backdrop -->
    <div class="modal-backdrop fade show" style="z-index: 1040;"></div>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: 'AdminConfigItemModal',

  props: {
    categories: {
      type: Object,
      required: true,
    },
    availableTypes: {
      type: Array,
      required: true,
    },
    permissions: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      isOpen: false,
      isSaving: false,
      loaded: false,
      mode: 'edit', // 'create' | 'edit'
      itemId: null,
      errors: {},
      form: this.defaultForm(),
    }
  },

  computed: {
    isEnumType() {
      return this.form.type === 'enum'
    },
    isFileType() {
      return ['file_plain', 'file_translated'].includes(this.form.type)
    },
  },

  methods: {
    defaultForm() {
      return {
        category: '',
        name: '',
        token: '',
        type: 'input_text_plain',
        enumOptions: [{ key: '', label: '' }],
        fileAllowedExtensions: '',
        fileMaxSizeKb: null,
      }
    },

    notify(message, level = 'success') {
      if (typeof this.flash === 'function') {
        this.flash(message, level)
      } else if (typeof window !== 'undefined' && typeof window.flash === 'function') {
        window.flash(message, level)
      } else {
        console.log(level.toUpperCase() + ': ' + message)
      }
    },

    // Abrir en modo creación
    openForCreate() {
      if (!this.permissions.create) return

      this.mode = 'create'
      this.itemId = null
      this.isOpen = true
      this.isSaving = false
      this.errors = {}
      this.form = this.defaultForm()
      this.loaded = true
    },

    // Abrir en modo edición
    openForEdit(itemId) {
      if (!this.permissions.edit) return

      this.mode = 'edit'
      this.itemId = itemId
      this.isOpen = true
      this.isSaving = false
      this.loaded = false
      this.errors = {}
      this.form = this.defaultForm()
      this.fetchItem()
    },

    close() {
      if (this.isSaving) return
      this.isOpen = false
    },

    addEnumOption() {
      this.form.enumOptions.push({ key: '', label: '' })
    },

    removeEnumOption(index) {
      this.form.enumOptions.splice(index, 1)
      if (this.form.enumOptions.length === 0) {
        this.form.enumOptions.push({ key: '', label: '' })
      }
    },

    loadFromItem(item) {
      this.form.category = item.category
      this.form.name = item.name
      this.form.token = item.token
      this.form.type = item.type

      const cfg = item.config || {}

      this.form.enumOptions = (cfg.options || []).map((opt) => ({
        key: opt.key || '',
        label: opt.label || '',
      }))

      if (this.form.enumOptions.length === 0) {
        this.form.enumOptions.push({ key: '', label: '' })
      }

      this.form.fileAllowedExtensions = cfg.file_allowed_extensions || ''
      this.form.fileMaxSizeKb = cfg.file_max_size_kb || null
    },

    buildConfigPayload() {
      const cfg = {}

      if (this.isEnumType) {
        const options = (this.form.enumOptions || []).filter((opt) => opt.key)
        cfg.options = options
      }

      if (this.isFileType) {
        if (this.form.fileAllowedExtensions) {
          cfg.file_allowed_extensions = this.form.fileAllowedExtensions
        }
        if (this.form.fileMaxSizeKb) {
          cfg.file_max_size_kb = this.form.fileMaxSizeKb
        }
      }

      return cfg
    },

    async fetchItem() {
      if (!this.itemId) {
        this.loaded = true
        return
      }

      try {
        const url = this.route('admin.config.show', { item: this.itemId })
        const { data } = await axios.get(url)
        const item = data.item || data
        this.loadFromItem(item)
        this.loaded = true
      } catch (e) {
        this.errors.__general =
          e.response?.data?.message ||
          'No se pudo cargar la variable.'
        this.loaded = true
      }
    },

    async submit() {
      if (this.mode === 'edit' && !this.permissions.edit) return
      if (this.mode === 'create' && !this.permissions.create) return

      this.isSaving = true
      this.errors = {}

      const payload = {
        category: this.form.category,
        name: this.form.name,
        token: this.form.token,
        type: this.form.type,
        config: this.buildConfigPayload(),
      }

      try {
        let url
        let method

        if (this.mode === 'create') {
          url = this.route('admin.config.store')
          method = axios.post
        } else {
          url = this.route('admin.config.update-definition', {
            item: this.itemId,
          })
          method = axios.put
        }

        const { data } = await method(url, payload)

        const msg =
          data.message ||
          (this.mode === 'create'
            ? 'Variable creada.'
            : 'Definición actualizada.')

        this.notify(msg, 'success')

        this.isOpen = false
        this.$emit(this.mode === 'create' ? 'created' : 'updated')
      } catch (e) {
        if (e.response?.status === 422 && e.response?.data?.errors) {
          const errs = e.response.data.errors
          this.errors = {
            category: errs.category?.[0],
            name: errs.name?.[0],
            token: errs.token?.[0],
            type: errs.type?.[0],
            __general: errs.config?.[0],
          }
        } else {
          this.errors.__general =
            e.response?.data?.message ||
            'No se pudo guardar la variable.'
        }
      } finally {
        this.isSaving = false
      }
    },
  },
}
</script>
