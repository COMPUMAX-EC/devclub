<!-- resources/js/components/admin/config/Index.vue -->
<template>
  <div>
    <!-- Modal de definiciones -->
    <admin-config-item-modal
      ref="itemModal"
      :categories="categories"
      :available-types="availableTypes"
      :permissions="permissions"
      @created="reloadItems"
      @updated="reloadItems"
    />

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
      <div>
        <h1 class="mb-0">Configuración</h1>
      </div>

      <button
        v-if="permissions.create"
        type="button"
        class="btn btn-sm btn-primary"
        @click="openCreateModal"
      >
        + Nueva variable
      </button>
    </div>

    <!-- Cargando -->
    <div v-if="isLoading" class="text-center py-10">
      <div class="spinner-border spinner-border-sm" role="status"></div>
      <span class="ms-2 small text-muted">Cargando variables...</span>
    </div>

    <!-- Listado -->
    <div v-else>
      <div
        v-for="(group, categoryKey) in groupedItems"
        :key="categoryKey"
        class="card mb-6"
      >
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
          <div>
            <div class="fw-semibold h4 mb-0">
              {{ categories[categoryKey] || categoryKey }}
            </div>
<!--            <div class="text-muted small">
              {{ categoryKey }}
            </div>-->
          </div>
        </div>

        <table class="table table-sm table-hover table-striped align-middle table-condensed">
          <tbody>
            <tr v-if="group.length === 0">
              <td colspan="3" class="text-muted small">
                No hay variables en esta categoría.
              </td>
            </tr>

            <tr v-for="item in group" :key="item.id">
              <td style="width: 25%;">
                <div class="main">{{ item.name }}</div>
<!--                <span class="small text-muted">{{ item.token }}</span>-->
              </td>

              <td style="width: 65%;">
                <!-- Valor editable / solo lectura según permisos -->
                <div v-if="permissions.fill">
                  <!-- INTEGER -->
                  <div v-if="isIntegerType(item)">
                    <input
                      type="text"
                      class="form-control form-control-sm"
                      v-model="item._intValue"
                      @input="onIntegerInput(item)"
                      inputmode="numeric"
                    />
                  </div>

                  <!-- BOOLEAN -->
                  <div v-else-if="isBooleanType(item)" class="form-check form-switch">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :id="'cfg-bool-' + item.id"
                      v-model="item._boolValue"
                      @change="onBooleanChange(item)"
                    />
                    <label
                      class="form-check-label small"
                      :for="'cfg-bool-' + item.id"
                    >
                      {{ item._boolValue ? 'Activo' : 'Inactivo' }}
                    </label>
                  </div>

                  <!-- DECIMAL -->
                  <div v-else-if="isDecimalType(item)">
                    <input
                      type="text"
                      class="form-control form-control-sm"
                      v-model="item._decimalValue"
                      @input="onDecimalInput(item)"
                      inputmode="decimal"
                    />
                  </div>

                  <!-- DATE -->
                  <div v-else-if="isDateType(item)">
                    <input
                      type="date"
                      class="form-control form-control-sm"
                      v-model="item._dateValue"
                      @change="onDateChange(item)"
                    />
                  </div>

                  <!-- ENUM -->
                  <div v-else-if="isEnumType(item)">
                    <select
                      class="form-select form-select-sm"
                      v-model="item._enumValue"
                      @change="onEnumChange(item)"
                    >
                      <option :value="null">-- Sin valor --</option>
                      <option
                        v-for="opt in enumOptions(item)"
                        :key="opt.key"
                        :value="opt.key"
                      >
                        {{ opt.label || opt.key }}
                      </option>
                    </select>
                  </div>

                  <!-- TEXT PLAIN / AREA / HTML -->
                  <div v-else-if="isPlainTextType(item)">
                    <textarea
                      v-if="isLongTextType(item)"
                      class="form-control form-control-sm"
                      rows="2"
                      v-model="item._textValue"
                      @input="onTextInput(item)"
                    ></textarea>
                    <input
                      v-else
                      type="text"
                      class="form-control form-control-sm"
                      v-model="item._textValue"
                      @input="onTextInput(item)"
                    />
                  </div>

                  <!-- TRANSLATED -->
                  <div v-else-if="isTranslatedType(item)">
                    <!-- Texto largo / HTML traducible: textarea -->
                    <div
                      v-if="item.type === 'textarea_translated' || item.type === 'html_translated'"
                      class="mb-1"
                    >
                      <textarea
                        class="form-control form-control-sm mb-1"
                        rows="2"
                        v-model="item._translations.es"
                        placeholder="Valor (ES)"
                        @input="onTranslationsChange(item)"
                      ></textarea>
                      <textarea
                        class="form-control form-control-sm"
                        rows="2"
                        v-model="item._translations.en"
                        placeholder="Valor (EN)"
                        @input="onTranslationsChange(item)"
                      ></textarea>
                    </div>

                    <!-- Texto corto traducible: input -->
                    <div v-else class="mb-1">
                      <input
                        type="text"
                        class="form-control form-control-sm mb-1"
                        v-model="item._translations.es"
                        placeholder="Valor (ES)"
                        @input="onTranslationsChange(item)"
                      />
                      <input
                        type="text"
                        class="form-control form-control-sm"
                        v-model="item._translations.en"
                        placeholder="Valor (EN)"
                        @input="onTranslationsChange(item)"
                      />
                    </div>
                  </div>

                  <!-- FILE PLAIN -->
                  <div v-else-if="isFilePlainType(item)">
                    <div class="input-group input-group-sm">
                      <a
                        :href="item.file_plain?.url || '#'"
                        target="_blank"
                        class="input-group-text form-control bg-white link-primary"
                        :class="{ 'text-muted': !item.file_plain }"
                      >
                        {{
                          item.file_plain
                            ? item.file_plain.original_name
                            : 'No hay archivo seleccionado'
                        }}
                      </a>

                      <button
                        type="button"
                        class="btn btn-sm btn-light-primary"
                        :disabled="item._uploadingPlain"
                        @click="openFileDialog(item, null)"
                      >
                        <span
                          v-if="item._uploadingPlain"
                          class="spinner-border spinner-border-sm"
                          role="status"
                        ></span>
                        <span v-else>Actualizar</span>
                      </button>

                      <button
                        v-if="item.value_file_plain_id"
                        type="button"
                        class="btn btn-sm btn-light-danger"
                        :disabled="item._uploadingPlain"
                        @click="clearFilePlain(item)"
                      >
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </div>

                  <!-- FILE TRANSLATED -->
                  <div v-else-if="isFileTranslatedType(item)">
                    <div class="row g-2">
                      <div class="col-12">
                        
                        <div class="input-group input-group-sm">
							<span class="input-group-text" style="min-width: 70px;">Español</span>
							<a
                            :href="item.file_es?.url || '#'"
                            target="_blank"
                            class="input-group-text form-control bg-white link-primary"
                            :class="{ 'text-muted': !item.file_es }"
                          >
                            {{
                              item.file_es
                                ? item.file_es.original_name
                                : 'No hay archivo seleccionado'
                            }}
                          </a>

                          <button
                            type="button"
                            class="btn btn-sm btn-light-primary"
                            :disabled="item._uploadingEs"
                            @click="openFileDialog(item, 'es')"
                          >
                            <span
                              v-if="item._uploadingEs"
                              class="spinner-border spinner-border-sm"
                              role="status"
                            ></span>
                            <span v-else>Actualizar</span>
                          </button>

                          <button
                            v-if="item.value_file_es_id"
                            type="button"
                            class="btn btn-sm btn-light-danger"
                            :disabled="item._uploadingEs"
                            @click="clearFileEs(item)"
                          >
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </div>

                      <div class="col-12">
                        <div class="input-group input-group-sm">
							<span class="input-group-text" style="min-width: 70px;">Inglés</span>
                          <a
                            :href="item.file_en?.url || '#'"
                            target="_blank"
                            class="input-group-text form-control bg-white link-primary"
                            :class="{ 'text-muted': !item.file_en }"
                          >
                            {{
                              item.file_en
                                ? item.file_en.original_name
                                : 'No hay archivo seleccionado'
                            }}
                          </a>

                          <button
                            type="button"
                            class="btn btn-sm btn-light-primary"
                            :disabled="item._uploadingEn"
                            @click="openFileDialog(item, 'en')"
                          >
                            <span
                              v-if="item._uploadingEn"
                              class="spinner-border spinner-border-sm"
                              role="status"
                            ></span>
                            <span v-else>Actualizar</span>
                          </button>

                          <button
                            v-if="item.value_file_en_id"
                            type="button"
                            class="btn btn-sm btn-light-danger"
                            :disabled="item._uploadingEn"
                            @click="clearFileEn(item)"
                          >
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Fallback -->
                  <div v-else class="text-muted small">
                    Tipo no editable aquí.
                  </div>
                </div>

                <!-- Sin permiso de fill: solo visualizar -->
                <div v-else>
                  <span v-if="isFileType(item)">
                    [archivo]
                  </span>
                  <span v-else-if="item.display_value !== null">
                    {{ item.display_value }}
                  </span>
                  <span v-else class="text-muted">
                    —
                  </span>
                </div>
              </td>

              <td style="width: 10%;" class="text-end">
                <button
                  v-if="permissions.edit"
                  type="button"
                  class="btn btn-icon btn-sm btn-light-primary me-1"
                  title="Editar definición"
                  @click="openEditModal(item)"
                >
                  <i class="bi bi-pencil"></i>
                </button>

                <button
                  v-if="permissions.delete"
                  type="button"
                  class="btn btn-icon btn-sm btn-light-danger"
                  title="Eliminar variable"
                  @click="deleteItem(item)"
                >
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <input
        ref="fileInput"
        type="file"
        class="d-none"
        :accept="currentFileAccept"
        @change="onFileSelected"
      />
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import * as format from '@/utils/format'
import AdminConfigItemModal from './ItemModal.vue'

export default {
  name: 'AdminConfigIndex',

  components: {
    AdminConfigItemModal,
  },

  props: {
    initialCategories: {
      type: Object,
      required: true,
    },
    initialPermissions: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      categories: { ...this.initialCategories },
      permissions: { ...this.initialPermissions },
      items: [],
      isLoading: true,
      autosaveTimers: {},
      autosaveDelay:
        (typeof window !== 'undefined' &&
          window.__RUNTIME_CONFIG__ &&
          window.__RUNTIME_CONFIG__.autosaveDelayMs) ||
        800,
      currentFileTarget: null, // { itemId, locale }
    }
  },

  computed: {
    availableTypes() {
      return [
        { value: 'integer', label: 'Entero' },
        { value: 'decimal', label: 'Decimal' },
        { value: 'boolean', label: 'Booleano' },
        { value: 'date', label: 'Fecha' },
        { value: 'input_text_plain', label: 'Texto simple' },
        { value: 'textarea_plain', label: 'Texto largo' },
        { value: 'html_plain', label: 'HTML plano' },
        { value: 'input_text_translated', label: 'Texto traducible' },
        { value: 'textarea_translated', label: 'Texto largo traducible' },
        { value: 'html_translated', label: 'HTML traducible' },
        { value: 'email', label: 'Email' },
        { value: 'url', label: 'URL' },
        { value: 'phone', label: 'Teléfono' },
        { value: 'color', label: 'Color' },
        { value: 'json', label: 'JSON (texto)' },
        { value: 'model_reference', label: 'Referencia a modelo' },
        { value: 'enum', label: 'Enum' },
        { value: 'file_plain', label: 'Archivo simple' },
        { value: 'file_translated', label: 'Archivo traducible ES/EN' },
      ]
    },

    groupedItems() {
      const groups = {}
      this.items.forEach((item) => {
        if (!groups[item.category]) {
          groups[item.category] = []
        }
        groups[item.category].push(item)
      })
      return groups
    },

    currentFileAccept() {
      if (!this.currentFileTarget) return null
      const item = this.items.find((i) => i.id === this.currentFileTarget.itemId)
      return item ? item._fileAccept || null : null
    },
  },

  mounted() {
    this.loadItems()
  },

  methods: {
    notify(message, level = 'success') {
      if (typeof this.flash === 'function') {
        this.flash(message, level)
      } else if (typeof window !== 'undefined' && typeof window.flash === 'function') {
        window.flash(message, level)
      } else {
        console.log(level.toUpperCase() + ': ' + message)
      }
    },

    typeLabel(type) {
      const t = this.availableTypes.find((x) => x.value === type)
      return t ? t.label : type
    },

    // ------------ helpers de tipo (JS) ------------

    isIntegerType(item) {
      return item.type === 'integer'
    },

    isBooleanType(item) {
      return item.type === 'boolean'
    },

    isDecimalType(item) {
      return item.type === 'decimal'
    },

    isDateType(item) {
      return item.type === 'date'
    },

    isEnumType(item) {
      return item.type === 'enum'
    },

    isPlainTextType(item) {
      return [
        'input_text_plain',
        'textarea_plain',
        'html_plain',
        'email',
        'url',
        'phone',
        'color',
        'json',
        'model_reference',
      ].includes(item.type)
    },

    isLongTextType(item) {
      return ['textarea_plain', 'html_plain'].includes(item.type)
    },

    isTranslatedType(item) {
      return [
        'input_text_translated',
        'textarea_translated',
        'html_translated',
      ].includes(item.type)
    },

    isFilePlainType(item) {
      return item.type === 'file_plain'
    },

    isFileTranslatedType(item) {
      return item.type === 'file_translated'
    },

    isFileType(item) {
      return this.isFilePlainType(item) || this.isFileTranslatedType(item)
    },

    enumOptions(item) {
      const cfg = item.config || {}
      return cfg.options || []
    },

    // ------------ restricciones de archivo ------------

    getFileConstraints(item) {
      const cfg = item.config || {}

      let exts = cfg.file_allowed_extensions || []
      if (typeof exts === 'string') {
        exts = exts
          .split(',')
          .map((e) => e.trim())
          .filter((e) => e.length)
      } else if (!Array.isArray(exts)) {
        exts = []
      }

      exts = exts
        .map((e) => String(e || '').toLowerCase().replace(/^\./, ''))
        .filter((e) => e.length)

      let maxSizeKb = cfg.file_max_size_kb
      if (typeof maxSizeKb === 'string') {
        const n = parseInt(maxSizeKb, 10)
        maxSizeKb = Number.isNaN(n) ? null : n
      } else if (typeof maxSizeKb !== 'number') {
        maxSizeKb = null
      }

      if (typeof maxSizeKb === 'number' && maxSizeKb <= 0) {
        maxSizeKb = null
      }

      return {
        extensions: exts,
        maxSizeKb,
      }
    },

    // ------------ carga / preparación ------------

    async loadItems() {
      this.isLoading = true
      try {
        const url = this.route('admin.config.index')
        const { data } = await axios.get(url)
        const items = data.items || []
        this.categories = data.categories || this.categories
        this.permissions = data.permissions || this.permissions
        this.items = items.map((it) => this.prepareItem(it))
      } catch (e) {
        this.notify('No se pudo cargar la configuración.', 'danger')
        this.items = []
      } finally {
        this.isLoading = false
      }
    },

    reloadItems() {
      this.loadItems()
    },

    prepareItem(raw) {
      const item = { ...raw }

      // INTEGER
      item._intValue =
        item.value_int != null ? format.formatInteger(item.value_int) : ''

      // BOOLEAN
      item._boolValue =
        item.type === 'boolean'
          ? item.value_int != null
            ? Boolean(item.value_int)
            : false
          : false

      // DECIMAL
      item._decimalValue =
        item.value_decimal != null ? format.formatDecimal(item.value_decimal) : ''

      // DATE
      item._dateValue = item.value_date || ''

      // TEXT
      item._textValue = item.value_text || ''

      // TRANSLATED
      item._translations = {
        es: item.value_translations?.es ?? '',
        en: item.value_translations?.en ?? '',
      }

      // ENUM
      item._enumValue = item.type === 'enum' ? item.value_text || null : null

      // FILE: accept basado en extensiones de config
      const { extensions } = this.getFileConstraints(item)
      if (extensions.length) {
        item._fileAccept = extensions
          .map((e) => (e.startsWith('.') ? e : '.' + e))
          .join(',')
      } else {
        item._fileAccept = null
      }

      item._uploadingPlain = false
      item._uploadingEs = false
      item._uploadingEn = false

      return item
    },

    replaceItemFromServer(raw) {
      if (!raw) return
      const prepared = this.prepareItem(raw)
      const idx = this.items.findIndex(
        (i) => String(i.id) === String(prepared.id),
      )

      if (idx === -1) {
        this.items.push(prepared)
        return
      }

      const existing = this.items[idx]
      Object.keys(prepared).forEach((key) => {
        existing[key] = prepared[key]
      })
    },

    // ------------ autosave ------------

    scheduleAutosave(item, payload, keySuffix) {
      const key = `${item.id}:${keySuffix}`
      if (this.autosaveTimers[key]) {
        clearTimeout(this.autosaveTimers[key])
      }
      this.autosaveTimers[key] = setTimeout(() => {
        this.saveValue(item, payload, key)
      }, this.autosaveDelay)
    },

    async saveValue(item, payload, key) {
      try {
        const url = this.route('admin.config.update-value', { item: item.id })
        const { data } = await axios.put(url, payload)
        this.replaceItemFromServer(data.item || data.data)
        this.notify('Cambios guardados.', 'success')
      } catch (e) {
        const msg =
          e.response?.data?.message || 'No se pudo guardar el valor.'
        this.notify(msg, 'danger')
      } finally {
        if (this.autosaveTimers[key]) {
          clearTimeout(this.autosaveTimers[key])
          delete this.autosaveTimers[key]
        }
      }
    },

    // ------------ handlers de edición ------------

    onIntegerInput(item) {
      const { value, display } = format.normalizeIntegerInput(item._intValue)
      item._intValue = display
      item.value_int = value
      this.scheduleAutosave(item, { value: value }, 'value')
    },

    onBooleanChange(item) {
      const v = !!item._boolValue
      item.value_int = v ? 1 : 0
      this.scheduleAutosave(item, { value: v }, 'value')
    },

    onDecimalInput(item) {
      const { normalized, display } = format.normalizeDecimalDigitsInput(
        item._decimalValue,
      )
      item._decimalValue = display
      item.value_decimal = normalized
      this.scheduleAutosave(item, { value: normalized }, 'value')
    },

    onDateChange(item) {
      this.scheduleAutosave(item, { value: item._dateValue || null }, 'value')
    },

    onEnumChange(item) {
      this.scheduleAutosave(item, { value: item._enumValue }, 'value')
    },

    onTextInput(item) {
      this.scheduleAutosave(item, { value: item._textValue }, 'value')
    },

    onTranslationsChange(item) {
      this.scheduleAutosave(item, { translations: item._translations }, 'translations')
    },

    // ------------ archivos (patrón PlanVersion + validación por config) ------------

    openFileDialog(item, locale) {
      this.currentFileTarget = {
        itemId: item.id,
        locale: locale || null,
      }

      const input = this.$refs.fileInput
      if (input) {
        input.value = ''
        input.click()
      }
    },

    async onFileSelected(event) {
      const file = event.target.files[0]
      if (!file || !this.currentFileTarget) {
        return
      }

      const { itemId, locale } = this.currentFileTarget
      const item = this.items.find((i) => i.id === itemId)
      if (!item) {
        event.target.value = ''
        this.currentFileTarget = null
        return
      }

      // Validación front según config (tamaño y extensiones)
      const { extensions, maxSizeKb } = this.getFileConstraints(item)

      // Tamaño
      const sizeKb = file.size / 1024
      if (maxSizeKb && sizeKb > maxSizeKb + 0.1) {
        this.notify(
          `El archivo excede el tamaño máximo permitido (${maxSizeKb} KB).`,
          'danger',
        )
        event.target.value = ''
        this.currentFileTarget = null
        return
      }

      // Extensión
      if (extensions.length) {
        const name = file.name || ''
        const dotIndex = name.lastIndexOf('.')
        const ext = dotIndex >= 0 ? name.slice(dotIndex + 1).toLowerCase() : ''
        if (!ext || !extensions.includes(ext)) {
          this.notify(
            `Extensión no permitida. Extensiones válidas: ${extensions.join(', ')}.`,
            'danger',
          )
          event.target.value = ''
          this.currentFileTarget = null
          return
        }
      }

      if (item.type === 'file_plain') {
        item._uploadingPlain = true
      } else if (locale === 'es') {
        item._uploadingEs = true
      } else {
        item._uploadingEn = true
      }

      try {
        const url = this.route('admin.config.upload-file', { item: item.id })
        const formData = new FormData()

        // El backend espera SIEMPRE "file" (+ opcional "locale")
        formData.append('file', file)
        if (item.type === 'file_translated' && locale) {
          formData.append('locale', locale)
        }

        const { data } = await axios.post(url, formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })

        // Preferimos usar un item completo si viene del backend
        const updated = data.item || data.data || null
        if (updated) {
          this.replaceItemFromServer(updated)
        } else {
          // Fallback: actualizar file_plain / file_es / file_en explícitamente
          if (item.type === 'file_plain') {
            const f = data.file_plain || data.file || null
            if (f) {
              item.file_plain = f
              if (f.id != null) {
                item.value_file_plain_id = f.id
              }
            }
          } else if (item.type === 'file_translated') {
            if (locale === 'es') {
              const f = data.file_es || data.file || null
              if (f) {
                item.file_es = f
                if (f.id != null) {
                  item.value_file_es_id = f.id
                }
              }
            } else if (locale === 'en') {
              const f = data.file_en || data.file || null
              if (f) {
                item.file_en = f
                if (f.id != null) {
                  item.value_file_en_id = f.id
                }
              }
            }
          }
        }

        const msg = data.message || 'Archivo subido correctamente.'
        this.notify(msg, 'success')
      } catch (e) {
        const msg =
          e.response?.data?.message ||
          'No se pudo subir el archivo.'
        this.notify(msg, 'danger')
      } finally {
        event.target.value = ''
        if (item.type === 'file_plain') {
          item._uploadingPlain = false
        } else if (locale === 'es') {
          item._uploadingEs = false
        } else {
          item._uploadingEn = false
        }
        this.currentFileTarget = null
      }
    },

    async clearFilePlain(item) {
      if (!item.value_file_plain_id) return
      const confirmed = window.confirm(
        '¿Seguro que deseas quitar el archivo?',
      )
      if (!confirmed) return

      item._uploadingPlain = true

      try {
        const url = this.route('admin.config.update-value', { item: item.id })
        const { data } = await axios.put(url, {
          value_file_plain_id: null,
        })

        const updated = data.item || data.data || null
        if (updated) {
          this.replaceItemFromServer(updated)
        }

        // Aseguramos estado local consistente para las etiquetas
        item.value_file_plain_id = null
        item.file_plain = null

        const msg = data.message || 'Archivo removido.'
        this.notify(msg, 'success')
      } catch (e) {
        const msg =
          e.response?.data?.message ||
          'No se pudo remover el archivo.'
        this.notify(msg, 'danger')
      } finally {
        item._uploadingPlain = false
      }
    },

    async clearFileEs(item) {
      if (!item.value_file_es_id) return
      const confirmed = window.confirm(
        '¿Seguro que deseas quitar el archivo (ES)?',
      )
      if (!confirmed) return

      item._uploadingEs = true

      try {
        const url = this.route('admin.config.update-value', { item: item.id })
        const { data } = await axios.put(url, {
          value_file_es_id: null,
        })

        const updated = data.item || data.data || null
        if (updated) {
          this.replaceItemFromServer(updated)
        }

        // Aseguramos estado local consistente para las etiquetas
        item.value_file_es_id = null
        item.file_es = null

        const msg =
          data.message || 'Archivo ES removido.'
        this.notify(msg, 'success')
      } catch (e) {
        const msg =
          e.response?.data?.message ||
          'No se pudo remover el archivo (ES).'
        this.notify(msg, 'danger')
      } finally {
        item._uploadingEs = false
      }
    },

    async clearFileEn(item) {
      if (!item.value_file_en_id) return
      const confirmed = window.confirm(
        '¿Seguro que deseas quitar el archivo (EN)?',
      )
      if (!confirmed) return

      item._uploadingEn = true

      try {
        const url = this.route('admin.config.update-value', { item: item.id })
        const { data } = await axios.put(url, {
          value_file_en_id: null,
        })

        const updated = data.item || data.data || null
        if (updated) {
          this.replaceItemFromServer(updated)
        }

        // Aseguramos estado local consistente para las etiquetas
        item.value_file_en_id = null
        item.file_en = null

        const msg =
          data.message || 'Archivo EN removido.'
        this.notify(msg, 'success')
      } catch (e) {
        const msg =
          e.response?.data?.message ||
          'No se pudo remover el archivo (EN).'
        this.notify(msg, 'danger')
      } finally {
        item._uploadingEn = false
      }
    },

    // ------------ acciones de definición ------------

    openCreateModal() {
      if (!this.permissions.create) return
      if (this.$refs.itemModal) {
        this.$refs.itemModal.openForCreate()
      }
    },

    openEditModal(item) {
      if (!this.permissions.edit) return
      if (this.$refs.itemModal) {
        this.$refs.itemModal.openForEdit(item.id)
      }
    },

    async deleteItem(item) {
      if (!this.permissions.delete) return

      const confirmed = window.confirm(
        `¿Seguro que deseas eliminar la variable "${item.name}"?`,
      )
      if (!confirmed) return

      try {
        const url = this.route('admin.config.destroy', { item: item.id })
        const { data } = await axios.delete(url)
        this.items = this.items.filter((i) => i.id !== item.id)
        const msg = data.message || 'Variable eliminada.'
        this.notify(msg, 'success')
      } catch (e) {
        const msg =
          e.response?.data?.message ||
          'No se pudo eliminar la variable.'
        this.notify(msg, 'danger')
      }
    },
  },
}
</script>
