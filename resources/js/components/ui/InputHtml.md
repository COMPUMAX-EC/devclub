# `<InputHtml>`

Ruta del componente: `resources/js/components/ui/InputHtml.vue`

---

## Props

- `v-model` / `modelValue`
  - Tipo: `String`
  - Descripción: Contenido HTML del editor.  
    Se sincroniza de forma diferida mediante el evento `update:modelValue` (con debounce interno).

- `id`
  - Tipo: `String` (opcional)
  - Descripción: Identificador del editor, útil para distinguirlo cuando hay varios en la misma vista.

- `name`
  - Tipo: `String` (opcional)
  - Descripción: Nombre lógico del campo, se envía junto con los eventos para identificar qué editor lo emitió.

- `placeholder`
  - Tipo: `String` (opcional)
  - Descripción: Texto de ayuda cuando el editor está vacío.

- `debounceMs`
  - Tipo: `Number` (opcional)
  - Descripción: Tiempo en milisegundos que se espera desde la última edición antes de emitir `update:modelValue`.
  - Valor por defecto: definido dentro del componente (por ejemplo `800` ms).

---

## Eventos

### `update:modelValue`
- Payload: `String` (HTML resultante).
- Descripción:  
  Se emite de forma diferida (tras `debounceMs`) cuando cambia el contenido.  
  Es el evento que utiliza `v-model` para mantener sincronizado el valor en el componente padre.

### `ready`
- Payload: `Object`
- Campos típicos:
  - `id`: `String|null` → corresponde al prop `id` si fue definido.
  - `name`: `String|null` → corresponde al prop `name` si fue definido.
  - (El componente puede incluir otros datos internos si se requiere.)
- Descripción:  
  Se emite una vez que el editor ha sido inicializado y está listo para usarse.

---

## Ejemplo completo de uso con `v-model` y eventos

```vue
<template>
  <div class="container py-5">
    <h1 class="mb-4">Ejemplo de uso de &lt;InputHtml&gt;</h1>

    <!-- Editor principal -->
    <InputHtml
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

    <h2 class="h5 mt-4">Último payload recibido en @ready:</h2>
    <pre class="bg-light p-3 small">{{ lastReadyInfo }}</pre>
  </div>
</template>

<script>
import InputHtml from '@/components/ui/InputHtml.vue'

export default {
  name: 'InputHtmlDemo',

  components: {
    InputHtml,
  },

  data() {
    return {
      form: {
        body: '<p>Contenido inicial de prueba.</p>',
      },
      lastUpdatedHtml: null,
      lastReadyInfo: null,
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
      this.lastReadyInfo = payload
      console.log('Editor listo:', payload)
    },
  },
}
</script>