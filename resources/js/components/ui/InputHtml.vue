<!-- resources/js/components/ui/InputHtml.vue -->
<template>
  <div class="input-html position-relative">
    <!-- CKEditor 4 reemplaza este textarea -->
    <textarea
      ref="textareaEl"
      :id="resolvedId"
      class="d-none"
    ></textarea>

    <!-- Placeholder visual (no se guarda en el HTML) -->
<!--    <div
      v-if="placeholder && isEmpty"
      class="input-html__placeholder"
      :style="{ top: placeholderTop }"
      @click="focusEditor"
    >
      {{ placeholder }}
    </div>-->

    <!-- Opcional, para formularios clásicos -->
    <input
      v-if="name"
      type="hidden"
      :name="name"
      :value="modelValue || ''"
    />
  </div>
</template>

<script>
import { markRaw } from 'vue'

let __ckeditor4LoaderPromise = null

export default {
  name: 'InputHtml',

  props: {
    modelValue: {
      type: String,
      default: '',
    },
    id: {
      type: String,
      default: null,
    },
    name: {
      type: String,
      default: null,
    },
    placeholder: {
      type: String,
      default: '',
    },
    debounceMs: {
      type: Number,
      default: 800,
    },

    /**
     * Altura del área editable (acepta cualquier unidad CSS: px, %, vh, rem, etc.)
     * Ej: "200px", "50%", "30vh"
     */
    height: {
      type: String,
      default: '200px',
    },

    /**
     * plain: pega como texto plano (sin formato)
     * basic: conserva formato básico permitido (ACF), sin imágenes ni estilos extra
     */
    pasteMode: {
      type: String,
      default: 'basic',
      validator: (v) => ['plain', 'basic'].includes(v),
    },
  },

  emits: ['update:modelValue', 'ready'],

  data() {
    return {
      resolvedId: this.id || `input-html-${Math.random().toString(36).slice(2, 10)}`,
      isReady: false,
      isSettingData: false,
      debounceTimer: null,
      isEmpty: true,
      placeholderTop: '14px',
    }
  },

  watch: {
    id(newVal) {
      // Mantener estable el id que CKEditor usa internamente.
      if (!this.isReady && newVal) {
        this.resolvedId = newVal
      }
    },

    height() {
      this.applyHeight()
      this.updatePlaceholderTop()
    },

    pasteMode(newVal) {
      const ed = this.getEditor()
      if (!ed || !this.isReady) return

      // Aplica a pegados posteriores
      ed.config.forcePasteAsPlainText = newVal === 'plain'
    },

    modelValue(newVal) {
      const ed = this.getEditor()
      if (!ed || !this.isReady) return
      if (this.isSettingData) return

      const incoming = this.normalizeHtml(newVal || '')
      const current = this.normalizeHtml(ed.getData() || '')

      if (incoming === current) return

      this.isSettingData = true
      ed.setData(incoming, () => {
        this.isSettingData = false
        this.updateEmptyState()
      })
    },
  },

  mounted() {
    this.initEditor()
  },

  beforeUnmount() {
    this.destroyEditor()
  },

  // compat Vue 2 si existiera en alguna parte del proyecto
  beforeDestroy() {
    this.destroyEditor()
  },

  methods: {
    getEditor() {
      return this.__editor || null
    },

    async initEditor() {
      if (this.getEditor() || this.isReady) return

      await this.loadCkeditor4()

      const CKEDITOR = window.CKEDITOR
      if (!CKEDITOR || typeof CKEDITOR.replace !== 'function') {
        throw new Error('CKEDITOR 4 no está disponible en window. Verifica /public/vendor/ckeditor4/ckeditor.js')
      }

      const el = this.$refs.textareaEl
      if (!el) return

      const config = {
        // Enter => <br>
        enterMode: CKEDITOR.ENTER_BR,
        shiftEnterMode: CKEDITOR.ENTER_BR,

        // Quita el chequeo/aviso de versión (banner/alerta)
        versionCheck: false,

        // Pegado
        forcePasteAsPlainText: this.pasteMode === 'plain',

        // Evita manejo de imágenes desde clipboard/drag&drop
        clipboard_handleImages: false,

        // Quitar plugins típicos asociados a imágenes/uploads/filebrowser si existiesen
        removePlugins: [
          'image',
          'image2',
          'uploadimage',
          'uploadwidget',
          'uploadfile',
          'filebrowser',
          'cloudservices',
          'easyimage',
        ].join(','),

        // Filtrado de contenido (ACF)
        // Permitimos indentación SOLO vía margin-left (para que Indent/Outdent funcione)
        allowedContent: this.allowedContentRules(),

        // Bloqueo explícito de recursos externos/no deseados
        disallowedContent: [
          'img',
          'figure',
          'picture',
          'source',
          'video',
          'audio',
          'iframe',
          'object',
          'embed',
          'style',
          'script',
          'link',
          'meta',
        ].join('; '),

        // Toolbar (sin Source)
        toolbar: [
          { name: 'styles', items: ['Format'] },
          { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
          { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent'] },
          { name: 'links', items: ['Link', 'Unlink'] },
          { name: 'cleanup', items: ['RemoveFormat'] },
        ],

        // Dropdown "Tipo de texto"
        format_tags: 'p;h1;h2;h3;h4',

        // Links: minimizar UI de atributos extra (dejamos solo href a nivel de filtrado)
        removeDialogTabs: 'link:advanced;link:target',

        // UX
        resize_enabled: true,
      }

      const editor = CKEDITOR.replace(el, config)
      this.__editor = markRaw(editor)

      editor.on('instanceReady', () => {
        // Contenido inicial
        const initial = this.normalizeHtml(this.modelValue || '')
        if (initial) {
          this.isSettingData = true
          editor.setData(initial, () => {
            this.isSettingData = false
            this.updateEmptyState()
            this.applyHeight()
            this.updatePlaceholderTop()
          })
        } else {
          this.updateEmptyState()
          this.applyHeight()
          this.updatePlaceholderTop()
        }

        // Pegado:
        // - plain: convertir SIEMPRE a texto plano (con <br>)
        // - basic: dejar pasar, pero remover tags de imagen típicos por seguridad adicional
        editor.on('paste', (evt) => {
          this.handlePaste(evt)
        })

        // Debounce de cambios (HTML)
        const schedule = () => this.scheduleEmit()
        editor.on('change', schedule)
        editor.on('key', schedule)
        editor.on('afterCommandExec', schedule)

        this.isReady = true
        this.$emit('ready', { id: this.id, name: this.name })
      })
    },

    destroyEditor() {
      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer)
        this.debounceTimer = null
      }

      const ed = this.getEditor()
      this.__editor = null
      this.isReady = false

      if (ed && typeof ed.destroy === 'function') {
        try {
          ed.destroy(true)
        } catch (e) {}
      }
    },

    scheduleEmit() {
      const ed = this.getEditor()
      if (!ed || !this.isReady) return
      if (this.isSettingData) return

      if (this.debounceTimer) clearTimeout(this.debounceTimer)

      this.debounceTimer = setTimeout(() => {
        const editor = this.getEditor()
        if (!editor || !this.isReady) return

        const html = this.normalizeHtml(editor.getData() || '')
        this.updateEmptyState(html)

        // 1er arg: HTML para v-model. 2do arg: meta (id/name)
        this.$emit('update:modelValue', html, { id: this.id, name: this.name })

        this.debounceTimer = null
      }, this.debounceMs)
    },

    handlePaste(evt) {
      const CKEDITOR = window.CKEDITOR
      if (!CKEDITOR) return

      const mode = this.pasteMode === 'basic' ? 'basic' : 'plain'

      // dataTransfer (si está disponible)
      const dt = evt?.data?.dataTransfer
      const getPlain = () => {
        try {
          if (dt && typeof dt.getData === 'function') {
            const t = dt.getData('text/plain')
            if (t !== undefined && t !== null) return String(t)
          }
        } catch (e) {}
        return null
      }

      if (mode === 'plain') {
        const plain = getPlain()
        const fallback = typeof evt?.data?.dataValue === 'string' ? this.stripHtml(evt.data.dataValue) : ''
        const text = plain !== null ? plain : fallback

        evt.data.type = 'html'
        evt.data.dataValue = this.plainTextToHtml(text)
        return
      }

      // basic:
      // - dejamos que CKEditor + ACF filtren el contenido
      // - removemos proactivamente tags típicos de imágenes (por si vienen en HTML pegado)
      if (evt?.data && typeof evt.data.dataValue === 'string') {
        let html = evt.data.dataValue

        html = html.replace(/<img\b[^>]*>/gi, '')
        html = html.replace(/<figure\b[^>]*>[\s\S]*?<\/figure>/gi, '')
        html = html.replace(/<picture\b[^>]*>[\s\S]*?<\/picture>/gi, '')

        evt.data.dataValue = html
      }
    },

    plainTextToHtml(text) {
      const CKEDITOR = window.CKEDITOR
      const raw = String(text ?? '')
        .replace(/\r\n/g, '\n')
        .replace(/\r/g, '\n')

      const encoded = CKEDITOR ? CKEDITOR.tools.htmlEncode(raw) : this.escapeHtml(raw)
      return encoded.replace(/\n/g, '<br>')
    },

    stripHtml(html) {
      return String(html || '')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/p>/gi, '\n')
        .replace(/<[^>]*>/g, '')
        .replace(/&nbsp;/gi, ' ')
        .trim()
    },

    escapeHtml(s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
    },

    updateEmptyState(optionalHtml = null) {
      const ed = this.getEditor()
      const html = optionalHtml !== null
        ? String(optionalHtml)
        : (ed ? String(ed.getData() || '') : String(this.modelValue || ''))

      const text = this.stripHtml(html)
      this.isEmpty = text.length === 0
    },

    focusEditor() {
      const ed = this.getEditor()
      if (ed && typeof ed.focus === 'function') {
        ed.focus()
      }
    },

    normalizeHtml(html) {
      if (!html) return ''
      const s = String(html)

      const compact = s.replace(/\s+/g, '').toLowerCase()
      if (
        compact === '' ||
        compact === '<br>' ||
        compact === '<p></p>' ||
        compact === '<p><br></p>' ||
        compact === '<p><br/></p>'
      ) {
        return ''
      }

      return s
    },

    allowedContentRules() {
      // “Básico” fácil y limpio:
      // h1–h4, p/br, strong/em/u (+ b/i), ul/ol/li, a[href], tablas sin estilos.
      // Indent/Outdent en CKEditor 4 suele usar margin-left inline -> permitimos SOLO margin-left.
      return [
        'h1 h2 h3 h4 p li{margin-left}',
        'br',
        'strong em u',
        'b i',
        'ul ol li',
        'a[!href]',
        'table',
        'thead tbody tfoot tr',
        'th[colspan,rowspan,scope]',
        'td[colspan,rowspan]',
      ].join('; ')
    },

    applyHeight() {
      const ed = this.getEditor()
      if (!ed || !ed.container || !ed.container.$) return

      const h = (this.height || '').trim()
      if (!h) return

      try {
        const root = ed.container.$
        const contents = root.querySelector('.cke_contents')
        if (contents) {
          contents.style.height = h
        }

        // Ayuda para que el iframe siga el alto del contenedor
        const frame = root.querySelector('iframe')
        if (frame) {
          frame.style.height = '100%'
        }
      } catch (e) {}
    },

    updatePlaceholderTop() {
      const ed = this.getEditor()
      if (!ed || !ed.container || !ed.container.$) return

      try {
        const root = ed.container.$
        const topBar = root.querySelector('.cke_top')
        const offset = topBar ? topBar.offsetHeight : 0
        // 12px de padding visual dentro del área editable
        this.placeholderTop = `${offset + 12}px`
      } catch (e) {
        this.placeholderTop = '14px'
      }
    },

    loadCkeditor4() {
      // Usa los estáticos en /public/vendor/ckeditor4
      if (window.CKEDITOR && typeof window.CKEDITOR.replace === 'function') {
        return Promise.resolve()
      }

      if (__ckeditor4LoaderPromise) return __ckeditor4LoaderPromise

      __ckeditor4LoaderPromise = new Promise((resolve, reject) => {
        const base = '/vendor/ckeditor4/'

        // Para que CKEditor resuelva plugins/skins relativos
        window.CKEDITOR_BASEPATH = base

        const src = base + 'ckeditor.js'

        const existing = Array.from(document.getElementsByTagName('script')).find(
          (s) => s.src && s.src.includes('/vendor/ckeditor4/ckeditor.js')
        )
        if (existing) {
          const wait = () => {
            if (window.CKEDITOR && typeof window.CKEDITOR.replace === 'function') return resolve()
            setTimeout(wait, 20)
          }
          return wait()
        }

        const s = document.createElement('script')
        s.src = src
        s.async = true
        s.onload = () => resolve()
        s.onerror = () => reject(new Error('No se pudo cargar CKEditor 4 desde: ' + src))
        document.head.appendChild(s)
      })

      return __ckeditor4LoaderPromise
    },
  },
}
</script>

<style scoped>
.input-html__placeholder {
  position: absolute;
  z-index: 5;
  left: 14px;
  right: 14px;
  color: #a1a5b7;
  cursor: text;
  user-select: none;
  pointer-events: auto;
}
</style>
