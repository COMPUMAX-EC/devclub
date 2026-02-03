<!-- resources/js/components/dev/InputHtmlDemo.vue (o la ruta que estés usando) -->
<template>
  <div class="container py-5">
    <h1 class="mb-4">Ejemplo de uso de &lt;InputHtml&gt;</h1>

    <!-- Editor principal -->
    <input-html
      v-model="form.body"
      id="body-editor"
      name="body"
      placeholder="Escribe aquí el contenido principal..."
      :debounce-ms="1000"
      @update:modelValue="onContentUpdated"
      @ready="onEditorReady"
    />

    <hr class="my-4" />

    <h2 class="h5">Valor HTML actual (form.body):</h2>
    <pre class="bg-light p-3 small">{{ form.body }}</pre>

    <h2 class="h5 mt-4">Último HTML recibido en @update:modelValue:</h2>
    <pre class="bg-light p-3 small">{{ lastUpdatedHtml }}</pre>

    <h2 class="h5 mt-4">Último payload recibido en @ready (simplificado):</h2>
    <pre class="bg-light p-3 small">{{ lastReadyInfo }}</pre>
  </div>
</template>

<script>
export default {
  name: 'DevInputHtmlDemo',

  data() {
    return {
      form: {
        body: '<p>Contenido inicial de prueba.</p>',
      },
      lastUpdatedHtml: null,

      // Solo datos planos que se puedan mostrar con {{ }}
      lastReadyInfo: null,

      // Si quieres conservar la referencia al editor, lo guardas aquí,
      // pero NO lo dibujes en el template.
      editorInstance: null,
    }
  },

  methods: {
    // Se dispara después del debounce interno del componente
    onContentUpdated(newHtml) {
      this.lastUpdatedHtml = newHtml
      console.log('update:modelValue =>', newHtml)
    },

    // Se dispara cuando el editor está listo
    onEditorReady(payload) {
      // Guardamos solo info simple para mostrar en pantalla
      this.lastReadyInfo = {
        id: payload.id || null,
        name: payload.name || null,
      }

      // Guardamos el editor real aparte (no se usa en el template)
      this.editorInstance = payload.editor || null

      console.log('Editor listo (payload completo):', payload)
    },
  },
}
</script>
