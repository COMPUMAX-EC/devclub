import { defineConfig, loadEnv } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path'

function parseFromAppUrl(appUrlRaw) {
  const fallback = 'http://localhost'
  const raw = (appUrlRaw && appUrlRaw.trim()) ? appUrlRaw.trim() : fallback
  try {
    const url = new URL(raw)
    return {
      protocol: url.protocol || 'http:',
      host: url.hostname || 'localhost',
      port: url.port ? Number(url.port) : undefined,
    }
  } catch {
    return { protocol: 'http:', host: 'localhost', port: undefined }
  }
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '') // incluye claves sin prefijo VITE_
  const app = parseFromAppUrl(env.APP_URL)

  const HOST   = env.VITE_HOST || app.host
  const PORT   = Number(env.VITE_PORT) || app.port || 5173
  const HTTPS  = false // (env.VITE_HTTPS === 'true') || (app.protocol === 'https:')
  const ORIGIN = env.VITE_DEV_SERVER_URL || `${HTTPS ? 'https' : 'http'}://${HOST}:${PORT}`

  // ★ Origen REAL de la página Laravel (lo que debe coincidir en el header CORS)
  const APP_ORIGIN = `${app.protocol}//${app.host}${app.port ? (':' + app.port) : ''}`

  return {
    resolve: {
      alias: {
        'vue': 'vue/dist/vue.esm-bundler.js',
        '@': path.resolve(__dirname, 'resources/js'),
      },
    },
    server: {
      host: HOST,
      port: PORT,
      strictPort: true,
      https: HTTPS || false,
      origin: ORIGIN, // URLs absolutas que Vite anuncia (HMR, módulos)
      cors: {
        origin: APP_ORIGIN,
        methods: ['GET', 'HEAD', 'OPTIONS'],
        credentials: false,
        allowedHeaders: ['*'],
      },

      // ★ Forzar el header por si un proxy agrega otro (nginx/Apache)
      headers: {
        'Access-Control-Allow-Origin': APP_ORIGIN,
        'Vary': 'Origin',
      },

      hmr: {
        host: HOST,
        port: PORT,
        protocol: HTTPS ? 'wss' : 'ws',
      },
    },
    plugins: [
      laravel({
        input: [
          'resources/js/app.js',
          'resources/css/app.css',
          'resources/css/custom.css',
        ],
        refresh: true,
      }),
      vue(),
    ],
    optimizeDeps: {
      include: ['ziggy-js'],
    },
  }
})
