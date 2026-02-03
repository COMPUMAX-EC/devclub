# `<CodeEditor>`

Componente Vue (CodeMirror 6) para edición de código con soporte multi-lenguaje y **un único “commit” por inactividad total**. El componente se integra con **`v-model` (Opción A)**: el padre recibe la actualización **solo cuando vence el debounce y hubo cambios**.

**Ruta:** `resources/js/components/ui/CodeEditor.vue`

---

## 1) Comportamiento

### 1.1 Edición
- Renderiza un editor CodeMirror.
- El contenido inicial se toma desde `modelValue`.
- El editor mantiene un valor interno `internalValue`.
- Cuando el documento cambia (edición), marca el estado como “dirty”.

### 1.2 Inactividad total (debounce)
El editor inicia/reinicia un timer solo si hay cambios pendientes. Se considera “actividad” del usuario (reinicia el timer si está dirty):

- teclado: `keydown`, `keyup`
- mouse: `mousedown`, `mouseup`, `click`
- rueda / scroll: `wheel`, `scroll`
- touch: `touchstart`, `touchmove`
- pointer move: `pointermove` (con throttle interno)

### 1.3 Commit (único)
Se hace commit cuando:

1. hubo cambios (`internalValue !== lastCommittedValue`)
2. ocurre inactividad total durante `debounceMs`
3. al vencimiento del timer, sigue habiendo cambios pendientes

En ese momento:
- se actualiza `lastCommittedValue`
- se emite **un único evento**: `update:modelValue`

---

## 2) `v-model`

Este componente está diseñado para que `v-model` reciba el valor **solo al commit** (por inactividad total), no en vivo.

### 2.1 Tipos soportados para `v-model`
`modelValue` puede ser:

#### A) `String`
- El editor edita ese string.
- En el commit emite: `update:modelValue` con el string final.

#### B) `Object` con propiedad `value`
- El editor edita `modelValue.value`.
- En el commit emite: `update:modelValue` con el **payload estricto** (ver sección 3).

**Nota importante:** si `modelValue` es objeto, debe contener la propiedad `value` (aunque sea `""`).

---

## 3) Payload estricto del `v-model` (solo 4 propiedades)

Cuando el padre usa `v-model` como **objeto** (con `value`), el componente emite exactamente:

~~~json
{
  "id": "id asignado",
  "name": "name asignado",
  "language": "php",
  "value": "<contenido final>"
}
~~~

- `id`: proviene de `props.id` (si no se define, `""`).
- `name`: proviene de `props.name` (si no se define, `""`).
- `language`: proviene de `props.language`.
- `value`: contenido final al momento del commit.

No se agregan otras propiedades.

---

## 4) Lenguajes soportados

Valores admitidos para `language`:

- `css`
- `html`
- `json`
- `markdown`
- `php`
- `xml`
- `yaml`
- `blade`

### 4.1 `blade`
- Base: HTML.
- Overlay simple para resaltar:
  - directivas `@...`
  - echos `{{ ... }}`
  - raw echos `{!! ... !!}`
  - comentarios `{{-- ... --}}`
- Autocompletado básico de directivas cuando se escribe `@` (si `autocomplete=true`).

---

## 5) TAB, readonly y disabled

- **Editable** (`disabled=false` y `readonly=false`):
  - `Tab` inserta un tab real `\t` (no se convierte a espacios).
  - `Shift+Tab` hace unindent.
- **Readonly** o **Disabled**:
  - el editor NO intercepta `Tab` (permite navegación normal del navegador).
  - `disabled` además evita interacción.

---

## 6) Props

### 6.1 Obligatorias

| Prop | Tipo | Obligatoria | Descripción |
|---|---|---:|---|
| `language` | `String` | Sí | Modo/lenguaje del editor. Valores: `css`, `html`, `json`, `markdown`, `php`, `xml`, `yaml`, `blade`. |

### 6.2 Opcionales

| Prop | Tipo | Default | Descripción |
|---|---|---:|---|
| `modelValue` | `String \| Object` | `""` | `v-model`. Puede ser `String` o `Object` con `value`. |
| `id` | `String` | `""` | Identificador lógico de la instancia (sale en payload cuando `modelValue` es objeto). |
| `name` | `String` | `""` | Nombre lógico de la instancia (sale en payload cuando `modelValue` es objeto). |
| `debounceMs` | `Number` | `1000` | Milisegundos de inactividad total para commitear y emitir el `v-model`. |
| `disabled` | `Boolean` | `false` | Deshabilita interacción. |
| `readonly` | `Boolean` | `false` | Solo lectura (foco/selección OK, edición NO). |
| `lineNumbers` | `Boolean` | `true` | Números de línea. |
| `highlightActive` | `Boolean` | `true` | Resalta línea activa. |
| `highlightOccurrences` | `Boolean` | `true` | Resalta ocurrencias (selección). |
| `autocomplete` | `Boolean` | `true` | Autocompletado (en Blade incluye directivas). |
| `autoClose` | `Boolean` | `true` | Autocierre de pares (brackets). |
| `autoIndent` | `Boolean` | `true` | Auto-indent en input. |
| `reindent` | `Boolean` | `true` | Mantiene indentOnInput (control conjunto con `autoIndent`). |
| `lint` | `Boolean` | `true` | Lint simple para `json`, `xml`, `blade` (heurístico). |

---

## 7) Ejemplos de uso

### 7.1 Ejemplo con TODOS los parámetros (v-model como objeto: payload estricto)

~~~vue
<template>
	<code-editor
		id="editor-1"
		name="template"
		language="blade"
		:debounce-ms="1000"
		:disabled="false"
		:readonly="false"
		:line-numbers="true"
		:highlight-active="true"
		:highlight-occurrences="true"
		:autocomplete="true"
		:auto-close="true"
		:auto-indent="true"
		:reindent="true"
		:lint="true"
		v-model="payload"
	/>

	<pre>{{ payload }}</pre>
</template>

<script setup>
import { ref } from 'vue';

const payload = ref({
	id: 'editor-1',
	name: 'template',
	language: 'blade',
	value: "@extends('layouts.app')\n\n@section('content')\n\t<div>Hola</div>\n@endsection\n",
});

// payload se actualiza SOLO tras inactividad total (debounceMs) y solo si hubo cambios.
</script>
~~~

### 7.2 Ejemplo mínimo (solo obligatorios)

#### A) `v-model` como string (solo value) + eventos

> Evento disponible: **`@update:modelValue`** (único evento; se emite tras inactividad total y solo si hubo cambios).

~~~vue
<template>
	<code-editor
		language="php"
		v-model="code"
		@update:modelValue="onCommit"
	/>
</template>

<script setup>
import { ref } from 'vue';

const code = ref("<?php\necho 'hola';\n");

function onCommit(value) {
	// `value` es el string final commiteado por inactividad.
	// Aquí puedes hacer debug, guardar, validar, etc.
	console.log('commit (php):', value);
}
</script>
~~~

#### B) sin `v-model` (solo lectura controlada por prop) + eventos

> Si no usas `v-model`, puedes escuchar **`@update:modelValue`** igualmente, pero en `readonly=true` no habrá commits por edición (no se edita).

~~~vue
<template>
	<code-editor
		language="json"
		:model-value="initial"
		:readonly="true"
		@update:modelValue="onCommit"
	/>
</template>

<script setup>
const initial = "{\n\t\"a\": 1\n}\n";

function onCommit(payloadOrValue) {
	// En readonly no debería emitirse por edición.
	// Si el padre cambia modelValue externamente, esto NO dispara el evento (solo commits internos).
	console.log('commit (json):', payloadOrValue);
}
</script>
~~~
