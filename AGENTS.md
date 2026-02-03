# Lineamientos de diseño (GFA)

## A) Arquitectura Frontend (Vue / AJAX / componentes)

1. **Operaciones por Vue + AJAX; modals con data fresca**\
   Todo se maneja con Vue y AJAX. Regla general: todo lo de Vue debe cargarse por **AJAX**, salvo casos donde **por seguridad** no sea conveniente.\
   Los modals que levantan datos deben obtener información **fresca** desde BD mediante AJAX: no precargar con Blade; el modal Vue debe pedir por AJAX lo necesario.

2. **Auto-registro de componentes Vue por \*\*\*\***\`\`\
   `resources/js/app.js` registra automáticamente componentes en `components`. Ej.: `./components/admin/users/Index.vue` → `AdminUsersIndex` y en Blade `<admin-users-index>`.

3. **Vue: estructura y convenciones de componentes**\
   Respetar `resources/js/components/admin/...` y la convención de nombres generada por `app.js` (p.ej. `AdminCountriesIndex`). No inventar otras convenciones/estructuras de montaje.

4. **Modal único reutilizable para crear/editar**\
   Un solo modal debe servir para **crear** y **editar**, y poder reutilizarse en diferentes vistas futuras.

5. **Modals “autónomos”: completan la operación y notifican solo resultados**\
   Los modals deben ser capaces de realizar **completamente** la operación solicitada:

   - Deben ejecutar ellos mismos todas las llamadas AJAX necesarias para completar la acción (crear/editar/eliminar/asignar, etc.).
   - Deben emitir eventos al componente padre **solo cuando efectivamente se realizó algo** (p.ej. “guardado exitoso”, “eliminado”, “asignado”).
   - En general, no es necesario notificar al padre cuando **no ocurrió nada** (p.ej. usuario presiona “Cancelar” y cierra el modal sin cambios).

## B) Routing y convenciones (Admin / Ziggy)

6. **Rutas y nombres en admin**\
   Usar rutas bajo `/admin/...` y nombres `admin.*` para el panel admin. Alinear nombres con lo que espera Ziggy y con los componentes Vue que las consumen.

## C) UI / Template / Assets (Craft / Bootstrap / patrones)

7. **Realm admin: layout base**\
   Para templates de rutas del realm admin usar `/layouts/craft.blade.php`.

8. **Template Craft: assets estáticos**\
   Craft con assets estáticos en `/public/assets`.

9. **Bootstrap y assets front**\
   No hacer `import "bootstrap"` dentro de componentes Vue. Asumir Bootstrap cargado desde estáticos en `public/` y usar solo clases CSS.

10. **Reutilización de patrones existentes (UX/UI)**\
    Si se pide “como el módulo de coberturas de plan-version”, replicar **exactamente** el patrón (card + tabla + modales AJAX para añadir/quitar), sin inventar un diseño nuevo.

11. **Referencias UI/JS globales, autosave/toasts y estilos**\
    Para variables/funciones globales, **guardado automático por AJAX**, **toasts**, **cards por bloques** y **estilos de tablas** a replicar, usar como referencia:

    - `resources/views/layouts/craft.blade.php`
    - `/resources/js/app.js`
    - `/resources/js/components/admin/plans/Edit.vue`

12. **Tablas con dropdowns: no usar \*\*\*\***\`\`\
    Si una tabla incluye cualquier **dropdown** (acciones, menús, selects con popover, etc.), **no** envolverla en contenedores con la clase `table-responsive`, porque el overflow recorta el contenido y el dropdown no se visualiza correctamente.

## D) Persistencia y reglas de dominio (BD / estados / borrados)

12. **Sin JSON nativo en BD**\
    BD sin columnas `JSON`: usar **TEXT** y manejar casting con `HasTranslatableJson`.

13. **Estados y borrados**\
    No redefinir estados/enums si ya hay patrón (p.ej. `DRAFT/ACTIVE/ARCHIVED`). No introducir borrado duro si el proyecto usa desactivar/archivar.

## E) i18n / Campos traducibles (HasTranslatableJson)

14. **Traducciones en BD: patrón HasTranslatableJson**\
    Usar `HasTranslatableJson` para guardar/leer campos multi-idioma.

15. **Textos multi-idioma: reglas estrictas**\
    Para textos multi-idioma, seguir el patrón: `HasTranslatableJson` + columna `TEXT` + `$casts` (para que `$item->name` entregue el idioma del usuario). No crear campos simples si deben existir ES/EN.

16. **Prohibido usar columnas JSON en la BD**\
    Todo traducible debe ir en `TEXT` y manejarse a nivel PHP con el trait.

17. **Traducciones en campos de BD solo si se pide explícitamente**\
    No decidir crear un campo traducible (ES/EN u otros) si no lo pides **expresamente**.

## F) Archivos / Uploads

18. **Almacenamiento/subida de archivos: servicio obligatorio**\
    Para guardar archivos usar \`\`.

19. **Patrón de subida de archivos y UI: referencia obligatoria**\
    Tomar como referencia: `PlanVersionController`, `UploadedFileService`, `/resources/js/components/admin/plans/Edit.vue`.\
    Replicar el input group: nombre, link, botón subir, botón eliminar.

## G) Proceso / alcance / supuestos

20. **Respetar código y contexto existente; no inventar estructura**\
    Respetar lo existente antes de proponer nuevas estructuras (models/migraciones/vistas). No inventar tablas/columnas ni re-modelar lo ya resuelto.

21. **Alcance de cambios / entregables**\
    Si se piden “correcciones en los archivos afectados”, entregar solo esos cambios, sin re-generar módulos completos ni cambiar decisiones previas salvo que se pida.

22. **No suponer columnas de BD**\
    No suponer nombres de columnas. Si no tienes el modelo o la estructura de la tabla, debes **pedirla**.

---

## Globales disponibles (Vue y HTML/Blade)

### VUE

```js
const runtime = window.__RUNTIME_CONFIG__ || {};

app.config.globalProperties.translate = window.translate
app.config.globalProperties.flash = window.flash
app.config.globalProperties.autosaveDelayMs = runtime.autosaveDelayMs ?? 800;

// ---- Mixin global: helper route() (Ziggy) disponible en TODOS los componentes ----
app.mixin({
  methods: {
    /**
     * route(name, params = {}, absolute = true)
     * Proxy a window.route inyectado por @routes (Ziggy)
     */
    route(name, params = {}, absolute = true) {
      if (typeof window !== 'undefined' && typeof window.route === 'function') {
        return window.route(name, params, absolute);
      }
      console.warn('Ziggy window.route() no está disponible, devolviendo "#"');
      return '#';
    },

  },
});
```

### HTML / BLADE

```php
@php
    // Locale efectivo (el mismo que resuelve BaseController)
    $locale = $currentLocale ?? app()->getLocale();

    $formatLocales   = config('format.locales', []);
    $fallbackLocale  = config('app.locale', 'es');

    $formatConfig =
        $formatLocales[$locale]
        ?? $formatLocales[$fallbackLocale]
        ?? [
            'number_locale'   => 'es-ES',
            'date_format'     => 'd/m/Y',
            'time_format'     => 'H:i',
            'datetime_format' => 'd/m/Y H:i',
            'js_date_format'  => 'dd/MM/yyyy',
        ];
@endphp

<script>
    // Config global de la app (sin fallbacks en los valores definidos aquí)
    window.__RUNTIME_CONFIG__ = Object.assign(window.__RUNTIME_CONFIG__ || {}, {
        autosaveDelayMs: @json(config('ui.autosave_delay_ms')),
    });

    window.appConfig = Object.assign(window.appConfig || {}, {
        locale:          @json($locale),
        numberLocale:    @json($formatConfig['number_locale']),
        dateFormat:      @json($formatConfig['date_format']),
        timeFormat:      @json($formatConfig['time_format']),
        dateTimeFormat:  @json($formatConfig['datetime_format']),
        jsDateFormat:    @json($formatConfig['js_date_format']),
        autosaveDelayMs: @json(config('ui.autosave_delay_ms')),
    });

    // Helper global para traducir campos JSON {es: "...", en: "..."}
    // Sin fallbacks entre idiomas: si falta el locale actual, devuelve string vacío.
    window.translate = function (value) {
        if (value == null) return '';
        if (typeof value === 'string') return value;

        const loc = window.appConfig.locale;
        const translated = value[loc];

        return translated == null ? '' : String(translated);
    };
</script>
```

---

## Inline edit + autosave (AJAX)

- Los campos que se editan y guardan inline deben usar el timeout `autosaveDelayMs()` descrito anteriormente para demorar el guardado por AJAX.
- La llamada AJAX de autosave:
  - **No debe reemplazar** el valor del elemento al regresar si el guardado fue exitoso; solo debe notificar éxito/fracaso con un **toast**.
  - En caso de **error**, **sí** debe reemplazar el elemento con el valor que viene de AJAX (para restaurar/corregir el estado).

---

## Toasts (global)

```js
/**
 * window.flash(message, type)
 * type: 'success' (verde), 'danger' (rojo), 'info', 'warning'
 */
window.flash = function(message, type = 'success')
```

