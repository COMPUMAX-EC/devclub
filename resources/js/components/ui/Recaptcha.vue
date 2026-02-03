<template>
  <div class="d-flex justify-content-center my-3">
    <div ref="captchaContainer"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, defineExpose } from 'vue'

const emit = defineEmits(['verify'])
const captchaContainer = ref(null)
let widgetId = null

// 🔁 Site key en runtime (inyectada por Blade)
const SITE_KEY =
  (typeof window !== 'undefined' && window.__RUNTIME_CONFIG__?.recaptchaSiteKey) ||
  null

function renderRecaptcha() {
  if (!captchaContainer.value || widgetId !== null || !SITE_KEY) return

  // Soporta estándar y enterprise si alguna está presente
  const api = window.grecaptcha?.render ? window.grecaptcha : window.grecaptcha?.enterprise
  if (!api?.render) return

  widgetId = api.render(captchaContainer.value, {
    sitekey: SITE_KEY,
    callback: (response) => emit('verify', response),
  })
}

function reset() {
  const api = window.grecaptcha?.reset ? window.grecaptcha : window.grecaptcha?.enterprise
  if (widgetId !== null && api?.reset) api.reset(widgetId)
}

defineExpose({ reset })

onMounted(() => {
  if (window.grecaptcha) {
    const ready = window.grecaptcha.ready || window.grecaptcha?.enterprise?.ready
    if (ready) return ready(() => renderRecaptcha())
    return renderRecaptcha()
  }
  // El script se cargará y llamará a initRecaptcha (definido abajo)
  window.initRecaptcha = () => {
    const ready = window.grecaptcha?.ready || window.grecaptcha?.enterprise?.ready
    if (ready) return ready(() => renderRecaptcha())
    renderRecaptcha()
  }
})

onBeforeUnmount(() => reset())
</script>
