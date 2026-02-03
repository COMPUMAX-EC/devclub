// resources/js/utils/format.js

// 🔹 Locale actual según preferencias del usuario (window.appConfig + <html lang>)
export function getCurrentLocale() {
  if (typeof window !== 'undefined' && window.appConfig?.locale) {
    const loc = String(window.appConfig.locale).toLowerCase()
    if (loc.startsWith('en')) return 'en'
    if (loc.startsWith('es')) return 'es'
  }

  const htmlLang = (document.documentElement.lang || 'es').toLowerCase()
  if (htmlLang.startsWith('en')) return 'en'
  if (htmlLang.startsWith('es')) return 'es'
  return 'es'
}

// 🔹 Locale para números (config/format.php -> number_locale)
export function getNumberLocale() {
  if (typeof window !== 'undefined' && window.appConfig?.numberLocale) {
    return window.appConfig.numberLocale
  }
  const loc = getCurrentLocale()
  return loc === 'en' ? 'en-US' : 'es-ES'
}

// 🔹 Formatters base (se crean al vuelo para respetar el numberLocale actual)
export function makeIntegerFormatter() {
  return new Intl.NumberFormat(getNumberLocale(), {
    maximumFractionDigits: 0,
    useGrouping: true,
  })
}

export function makeDecimalFormatter() {
  return new Intl.NumberFormat(getNumberLocale(), {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    useGrouping: true,
  })
}

// =========================
//  Enteros
// =========================

// Formatea un entero para vista ("123456" -> "123.456"/"123,456")
export function formatInteger(value) {
  if (value === null || value === undefined || value === '') return ''
  const n =
    typeof value === 'number'
      ? value
      : parseInt(String(value).replace(/\D/g, ''), 10)
  if (Number.isNaN(n)) return ''
  return makeIntegerFormatter().format(n)
}

// Normaliza un input de enteros (string) devolviendo
// { value: number|null, display: string }
export function normalizeIntegerInput(viewValue) {
  const raw = (viewValue || '').replace(/[^\d]/g, '')
  if (!raw) {
    return { value: null, display: '' }
  }
  const n = parseInt(raw, 10)
  if (Number.isNaN(n)) {
    return { value: null, display: '' }
  }
  const display = formatInteger(n)
  return { value: n, display }
}

// =========================
//  Decimales tipo "centavos"
// =========================

// Formatea un decimal normalizado ("1234.56" o number) para vista
// -> "1.234,56" o "1,234.56" según locale
export function formatDecimal(value) {
  if (value === null || value === undefined || value === '') return ''

  const n = typeof value === 'number'
    ? value
    : parseFloat(String(value))

  if (Number.isNaN(n)) return ''

  return makeDecimalFormatter().format(n)
}

// Normaliza un input de decimales donde el usuario SOLO escribe dígitos
// y siempre interpretamos como centavos (2 decimales):
// "1"   -> 0,01
// "12"  -> 0,12
// "123" -> 1,23
// Devuelve { normalized: "1234.56" | null, display: string }
export function normalizeDecimalDigitsInput(viewValue) {
  // viewValue = lo que hay en el input (puede tener separadores, etc.)
  const view = viewValue || ''

  // 1) Nos quedamos SOLO con dígitos
  const digits = (view.match(/\d/g) || []).join('')

  if (!digits) {
    return { normalized: null, display: '' }
  }

  // 2) Interpretamos siempre como "centavos" (2 decimales fijos)
  let d = digits
  if (d.length === 1) d = '0' + d
  if (d.length === 2) d = '0' + d

  const intPartRaw = d.slice(0, -2)
  const decPart = d.slice(-2)

  const intPartNum = parseInt(intPartRaw, 10)
  const intPart = Number.isNaN(intPartNum) ? 0 : intPartNum

  const normalized = `${intPart}.${decPart}` // siempre punto y 2 decimales

  const num = parseFloat(normalized)
  const display = Number.isNaN(num) ? '' : formatDecimal(num)

  return { normalized, display }
}

// =========================
//  Fechas / horas
//  (SIN Date(), SIN TZ; si falla => "error")
// =========================

export function getDateFormat() {
  return (typeof window !== 'undefined' && window.appConfig?.dateFormat)
    ? window.appConfig.dateFormat
    : 'd/m/Y'
}

export function getTimeFormat() {
  return (typeof window !== 'undefined' && window.appConfig?.timeFormat)
    ? window.appConfig.timeFormat
    : 'H:i'
}

export function getDateTimeFormat() {
  return (typeof window !== 'undefined' && window.appConfig?.dateTimeFormat)
    ? window.appConfig.dateTimeFormat
    : 'd/m/Y H:i'
}

//export function getJsDateFormat() {
//  return (typeof window !== 'undefined' && window.appConfig?.jsDateFormat)
//    ? window.appConfig.jsDateFormat
//    : 'dd/MM/yyyy'
//}

function _toStrStrict(value) {
  if (value === null || value === undefined) return null
  const s = String(value).trim()
  return s === '' ? null : s
}

/**
 * Lee fecha/hora desde strings típicos del backend (sin TZ real):
 * - "YYYY-MM-DD"
 * - "YYYY-MM-DD HH:MM:SS"
 * - "YYYY-MM-DDTHH:MM:SS(.sss)Z"
 * - "YYYY-MM-DDTHH:MM:SS(.sss)-03:00"
 *
 * Se ignoran Z/offset: se toma el bloque YYYY-MM-DD y, si existe, HH:MM:SS.
 */
function _parseBackendDateTime(value) {
  const s = _toStrStrict(value)
  if (!s) return null

  // Fecha
  const mDate = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!mDate) return null

  const yyyy = mDate[1]
  const mm = mDate[2]
  const dd = mDate[3]

  // Hora (opcional)
  // Busca tras 'T' o espacio (y antes de cualquier TZ/offset)
  const mTime = s.match(/[T\s](\d{2}):(\d{2})(?::(\d{2}))?/)
  const hh = mTime ? mTime[1] : null
  const ii = mTime ? mTime[2] : null
  const ss = mTime ? (mTime[3] ?? '00') : null
  return { yyyy, mm, dd, hh, ii, ss }
}

/**
 * Aplica un formato estilo PHP (d/m/Y H:i:s) sin Date().
 * Tokens soportados (estrictos):
 *   d m Y H i s
 * Escape: \ (ej: \d imprime "d")
 * Si el formato incluye letras no soportadas => "error"
 */
function _applyPhpLikeFormat(fmt, parts, requireTime = false) {
  const f = _toStrStrict(fmt)
  if (!f) return 'error'

  if (!parts) return 'error'
  if (requireTime && (!parts.hh || !parts.ii)) return 'error'

  let out = ''
  for (let i = 0; i < f.length; i++) {
    const ch = f[i]

    // =========================
    //  Escape PHP-style: \d imprime "d"
    // =========================
    if (ch === '\\') {
      i++
      if (i >= f.length) return 'error'
      out += f[i]
      continue
    }

    // =========================
    //  Tokens MULTICARÁCTER
    // =========================

    // "dd" -> día con cero (01–31)
    if (f.slice(i, i + 2) === 'dd') {
      if (!parts.dd) return 'error'
      out += parts.dd
      i += 1 // consumimos 2 caracteres
      continue
    }

    // "MM" -> mes con cero (01–12)
    if (f.slice(i, i + 2) === 'MM') {
      if (!parts.mm) return 'error'
      out += parts.mm
      i += 1
      continue
    }

    // "yyyy" -> año 4 dígitos
    if (f.slice(i, i + 4) === 'yyyy') {
      if (!parts.yyyy) return 'error'
      out += parts.yyyy
      i += 3
      continue
    }

    // =========================
    //  Tokens de UN carácter
    // =========================

    // Día con cero (01–31)
    if (ch === 'd') {
      if (!parts.dd) return 'error'
      out += parts.dd
      continue
    }

    // Mes con cero (01–12)
    if (ch === 'm') {
      if (!parts.mm) return 'error'
      out += parts.mm
      continue
    }

    // Año 4 dígitos (2026)
    if (ch === 'Y') {
      if (!parts.yyyy) return 'error'
      out += parts.yyyy
      continue
    }

    // Año 2 dígitos (26)
    if (ch === 'y') {
      if (!parts.yyyy) return 'error'
      out += parts.yyyy.slice(-2)
      continue
    }

    // Día sin cero inicial (1–31)
    if (ch === 'j') {
      if (!parts.dd) return 'error'
      const dNum = parseInt(parts.dd, 10)
      if (Number.isNaN(dNum)) return 'error'
      out += String(dNum)
      continue
    }

    // Mes sin cero inicial (1–12)
    if (ch === 'n') {
      if (!parts.mm) return 'error'
      const mNum = parseInt(parts.mm, 10)
      if (Number.isNaN(mNum)) return 'error'
      out += String(mNum)
      continue
    }

    // Hora 24h 00–23
    if (ch === 'H') {
      if (!parts.hh) return 'error'
      out += parts.hh
      continue
    }

    // Hora 12h 01–12
    if (ch === 'h') {
      if (!parts.hh) return 'error'
      const h24 = parseInt(parts.hh, 10)
      if (Number.isNaN(h24)) return 'error'
      let h12 = h24 % 12
      if (h12 === 0) h12 = 12
      const hh12 = h12 < 10 ? '0' + h12 : String(h12)
      out += hh12
      continue
    }

    // Minutos 00–59
    if (ch === 'i') {
      if (!parts.ii) return 'error'
      out += parts.ii
      continue
    }

    // Segundos 00–59
    if (ch === 's') {
      if (!parts.ss) return 'error'
      out += parts.ss
      continue
    }

    // AM / PM (en base a hora 24h)
    if (ch === 'A') {
      if (!parts.hh) return 'error'
      const h24 = parseInt(parts.hh, 10)
      if (Number.isNaN(h24)) return 'error'
      out += h24 < 12 ? 'AM' : 'PM'
      continue
    }

    // =========================
    //  Validación de letras
    // =========================

    // Si es letra y no es token soportado => error (sin fallbacks)
    if (/[A-Za-z]/.test(ch)) return 'error'

    // Separadores / espacios / signos
    out += ch
  }

  return out
}

/**
 * formatDate(value): usa getDateFormat()
 * Acepta DATE o DATETIME/ISO (usa solo la parte de fecha).
 * Si falla => "error"
 */
export function formatDate(value) {
  const parts = _parseBackendDateTime(value)
  if (!parts) return 'error'
  return _applyPhpLikeFormat(getDateFormat(), parts, false)
}

/**
 * formatDatetime(value): usa getDateTimeFormat()
 * Requiere que exista HH:MM en el input (SQL/ISO).
 * Si falla => "error"
 */
export function formatDatetime(value) {
  const parts = _parseBackendDateTime(value)
  if (!parts) return 'error'
  // Requiere hora/minuto presentes en el string
  if (!parts.hh || !parts.ii) return 'error'
  return _applyPhpLikeFormat(getDateTimeFormat(), parts, true)
}

/**
 * formatMonth(value): "MM/yyyy" (m/Y) SIEMPRE (requerimiento).
 * Si falla => "error"
 */
export function formatMonth(value) {
  const parts = _parseBackendDateTime(value)
  if (!parts) return 'error'
  return _applyPhpLikeFormat('m/Y', parts, false)
}
