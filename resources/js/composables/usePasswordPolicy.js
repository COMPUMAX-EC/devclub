export async function usePasswordPolicy() {
  const res = await fetch('/api/password-policy', { credentials: 'same-origin' });
  const policy = await res.json();

  function test(password, context = {}) {
    const results = {
      lengthMin: (password?.length || 0) >= policy.min,
      lengthMax: policy.max ? (password?.length || 0) <= policy.max : true,
      upper: !policy.require.uppercase || /[A-Z]/.test(password),
      lower: !policy.require.lowercase || /[a-z]/.test(password),
      number: !policy.require.numbers   || /\d/.test(password),
      symbol: !policy.require.symbols   || /[^A-Za-z0-9]/.test(password),
      mixed : !policy.require.mixed_case || (/[A-Z]/.test(password) && /[a-z]/.test(password)),
      noPersonal: true,
    };

    // No incluir datos personales
    const lowers = (password || '').toLowerCase();
    const checks = [
      (context.first_name || '').toLowerCase(),
      (context.last_name || '').toLowerCase(),
      (context.display_name || '').toLowerCase(),
      (context.email || '').toLowerCase().split('@')[0],
    ].filter(Boolean);

    results.noPersonal = !checks.some(str => str && lowers.includes(str));

    results.valid = Object.values(results).every(Boolean);
    return results;
  }

  return { policy, test };
}
