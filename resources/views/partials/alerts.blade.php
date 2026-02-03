<!-- Contenedor de toasts (global) -->
<div id="toast-stack" class="position-fixed bottom-0 end-0 p-3" style="z-index:1080"></div>

<script>
/**
 * window.flash(message, type)
 * type: 'success' (verde), 'danger' (rojo), 'info', 'warning'
 */
window.flash = function(message, type = 'success') {
  const container = document.getElementById('toast-stack');
  const el = document.createElement('div');
  // classes Craft/Bootstrap
  el.className = `toast align-items-center text-bg-${type} border-0 mb-2`;
  el.setAttribute('role', 'alert');
  el.setAttribute('aria-live', 'assertive');
  el.setAttribute('aria-atomic', 'true');

  // MINIMO CAMBIO: normalizar mensaje y convertir saltos de línea en <br>
  const htmlMessage = String(message ?? '').replace(/\n/g, '<br>');

  el.innerHTML = `
    <div class="d-flex">
      <div class="toast-body" style="color:#FFF; font-size:10pt;">${htmlMessage}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>`;
  container.appendChild(el);
  const toast = new bootstrap.Toast(el, { delay: 2400, autohide: true });
  toast.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
};

// Mostrar flashes de servidor (status/errores) automáticamente
</script>

@if (session('status'))
<script>
  window.addEventListener('DOMContentLoaded', () => flash(@json(session('status')), 'success'));
</script>
@endif

@if ($errors->any())
<script>
  window.addEventListener('DOMContentLoaded', () => 
    flash(@json(implode("\n", $errors->all())), 'danger')
  );
</script>
@endif
