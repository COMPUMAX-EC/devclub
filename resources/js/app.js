import { createApp } from 'vue'

const app = createApp({});

const runtime = window.__RUNTIME_CONFIG__ || {};

app.config.globalProperties.translate = window.translate
app.config.globalProperties.flash = window.flash
app.config.globalProperties.autosaveDelayMs = runtime.autosaveDelayMs ?? 800;

// Paginaciones estándar
app.config.globalProperties.perPageShort  = runtime.perPageShort  ?? 5;
app.config.globalProperties.perPageMedium = runtime.perPageMedium ?? 10;
app.config.globalProperties.perPageLarge  = runtime.perPageLarge  ?? 15;

// Permisos del usuario expuestos desde RUNTIME_CONFIG
app.config.globalProperties.abilities = runtime.abilities || {};

// Utilidad para PascalCase a partir de rutas/strings
const toPascal = (s) =>
  s
    .replace(/\.vue$/i, '')
    .replace(/[^a-zA-Z0-9/_-]/g, '')
    .split(/[\/_-]/)
    .filter(Boolean)
    .map(w => w.charAt(0).toUpperCase() + w.slice(1))
    .join('')

const modules = import.meta.glob('./components/**/*.vue', { eager: true });

console.log(modules);

Object.entries(modules).forEach(([path, mod]) => {
  // path p.ej.: "./components/ui/Toast.vue"  ó "./components/admin/users/Index.vue"
  const rel = path.replace('./components/', '');

  let name
  if (rel.startsWith('ui/')) {
    // UI: nombre corto por archivo (Toast.vue -> <Toast>, Modal.vue -> <Modal>)
    const file = rel.split('/').pop() // "Toast.vue"
    name = toPascal(file)             // "Toast"
  } else {
    // Features: nombre por ruta completa (admin/users/Index.vue -> <AdminUsersIndex>)
    name = toPascal(rel)              // "AdminUsersIndex"
  }

  // Registro global
  app.component(name, mod.default)

  // (Opcional en dev) console.debug('Registrado:', name, '->', path)
});

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

    /**
     * can(ability)
     * Usa las abilities expuestas en app.config.globalProperties.abilities
     */
    can(ability) {
      if (!ability) {
        return false;
      }

      const abilities = app.config.globalProperties.abilities;

      if (!abilities) {
        return false;
      }

      if (Object.prototype.hasOwnProperty.call(abilities, ability)) {
        return !!abilities[ability];
      }

      return false;
    },
  },
});

const comps = Object.keys(app._context.components || {})
console.log('Vue components registrados:', comps)

app.mount('#app')
