
# `<Select2>` (Vue wrapper para jQuery Select2) — ultra simplificado

## Archivo / registro
- Ruta: `/resources/js/components/ui/Select2.vue`
- Se usa como: `<select2>` (auto-registrado)

## Props (lo mínimo)
- `v-model` (`modelValue`): `String | Number | null`  
  Valor seleccionado.
- `:options`: `Array | Object`  
  - Modo A (recomendado): array de objetos tal como viene de BD.  
  - Modo B: mapa `{ value: label }` (solo si no usas grupos).
- `value-field` (default `"id"`): campo usado como valor (solo Modo A).
- `label-field` (default `"name"`): campo usado como texto visible (Modo A) / label del mapa (Modo B).
- `group-field` (default `null`): campo para agrupar (solo Modo A).
- `placeholder` (default `""`): texto cuando está vacío.
- `nullable` (default `true`): permite volver a `null` desde la UI.
- `search-enabled` (default `true`): muestra buscador.
- `accent-insensitive` (default `true`): búsqueda sin tildes.
- `disabled` (default `false`)

## Eventos (lo mínimo)
- `@update:modelValue="..."`  
  Emite `null | Number | String`
- `@select2-change="..."`  
  Emite info extra (value, rawValue, option normalizada, originalOption, index)

---

## Mini ejemplo (Modo A: array de objetos)

```vue
<template>
  <select2
    v-model="countryId"
    :options="countries"
    value-field="id"
    label-field="name"
    group-field="continent_name"
    placeholder="Seleccione un país"
    :nullable="true"
    :search-enabled="true"
    :accent-insensitive="true"
    @select2-change="onCountryChange"
  />
</template>

<script>
export default {
  data() {
    return {
      countryId: null,
      countries: [
        { id: 1, name: { es: 'Chile', en: 'Chile' }, continent_name: { es: 'América', en: 'America' } },
        { id: 2, name: { es: 'Argentina', en: 'Argentina' }, continent_name: { es: 'América', en: 'America' } },
      ],
    };
  },
  methods: {
    onCountryChange(payload) {
      // payload.value / payload.option / payload.originalOption
      window.flash('País actualizado', 'success');
    },
  },
};
</script>


## Mini ejemplo (Modo B: mapa value => label, sin grupos)

```vue
<template>
  <select2
    v-model="iso2"
    :options="isoMap"
    placeholder="Seleccione país"
  />
</template>

<script>
export default {
  data() {
    return {
      iso2: null,
      isoMap: {
        CL: 'Chile',
        AR: 'Argentina',
      },
    };
  },
};
</script>
