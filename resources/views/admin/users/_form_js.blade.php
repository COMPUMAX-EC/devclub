@push('vendor_entries')
<script>
(function(){
  const $ = (sel, ctx=document) => ctx.querySelector(sel);
  const $$ = (sel, ctx=document) => Array.from(ctx.querySelectorAll(sel));

  const hasRole = (role) => !!$('.role-check[data-role="'+role+'"]:checked');

  function syncVendorBlocks(){
    const wrap = $('#vendorFields');
    const showReg = hasRole('vendedor_regular');
    const showCap = hasRole('vendedor_capitados');
    const any = showReg || showCap;

    wrap.style.display = any ? '' : 'none';

    $('#vendorRegular').style.display = showReg ? '' : 'none';
    $('#vendorCapitados').style.display = showCap ? '' : 'none';

    // Reglas de required dinámicas
    const regFirst = $('[name="commission_regular_first_year_pct"]');
    const regRen   = $('[name="commission_regular_renewal_pct"]');
    const capPct   = $('[name="commission_capitados_pct"]');

    if (regFirst) regFirst.required = showReg;
    if (regRen)   regRen.required   = showReg;
    if (capPct)   capPct.required   = showCap;
  }

  function clampPercentInput(e){
    const el = e.target;
    if (!el || el.type !== 'number') return;
    const min = 0, max = 100;
    let v = parseFloat(el.value);
    if (Number.isNaN(v)) return;
    if (v < min) v = min;
    if (v > max) v = max;
    el.value = v.toFixed(2);
  }

  // Inicializar
  document.addEventListener('DOMContentLoaded', function(){
    $$('.role-check').forEach(cb => cb.addEventListener('change', syncVendorBlocks));
    $$('#vendorFields input[type="number"]').forEach(i => i.addEventListener('blur', clampPercentInput));
    syncVendorBlocks();
  });
})();
</script>
@endpush
