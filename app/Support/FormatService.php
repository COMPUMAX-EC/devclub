<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class FormatService
{
    protected string $locale;
    protected array $config;

    /**
     * @param string|null $locale Locale forzable (ej: 'es', 'en').
     *                            Si es null, se resuelve del usuario logueado / app().
     */
    public function __construct(?string $locale = null)
    {
        $this->locale = $this->resolveLocale($locale);
        $this->config = $this->resolveConfigForLocale($this->locale);
    }

    /**
     * Locale efectivo que está usando este servicio.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Config cruda que viene de config/format.php para este locale.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Formatea una fecha (solo fecha).
     *
     * @param  CarbonInterface|\DateTimeInterface|int|string|null $value
     * @param  string|null                                        $format  Si es null, usa date_format del config.
     * @return string|null
     */
    public function date($value, ?string $format = null): ?string
    {
        $carbon = $this->toCarbon($value);
        if (! $carbon) {
            return null;
        }

        $format ??= $this->config['date_format'] ?? 'Y-m-d';

        return $carbon->format($format);
    }

    /**
     * Formatea una hora.
     *
     * @param  CarbonInterface|\DateTimeInterface|int|string|null $value
     * @param  string|null                                        $format  Si es null, usa time_format del config.
     * @return string|null
     */
    public function time($value, ?string $format = null): ?string
    {
        $carbon = $this->toCarbon($value);
        if (! $carbon) {
            return null;
        }

        $format ??= $this->config['time_format'] ?? 'H:i';

        return $carbon->format($format);
    }

    /**
     * Formatea fecha + hora.
     *
     * @param  CarbonInterface|\DateTimeInterface|int|string|null $value
     * @param  string|null                                        $format  Si es null, usa datetime_format del config.
     * @return string|null
     */
    public function datetime($value, ?string $format = null): ?string
    {
        $carbon = $this->toCarbon($value);
        if (! $carbon) {
            return null;
        }

        $format ??= $this->config['datetime_format'] ?? 'Y-m-d H:i';

        return $carbon->format($format);
    }

    /**
     * Entero con separador de miles (sin decimales).
     *
     * Ejemplos:
     *  - es-ES: 12.345
     *  - en-US: 12,345
     *
     * @param  int|float|string|null $value
     * @param  bool                  $nullable  true => null si $value es null, false => "0"
     * @return string|null
     */
    public function integer($value, bool $nullable = true): ?string
    {
        if ($value === null) {
            return $nullable ? null : '0';
        }

        $numeric = (int) round((float) $value);

        // Si tenemos NumberFormatter, lo usamos.
        if (class_exists(\NumberFormatter::class)) {
            $locale = $this->config['number_locale'] ?? $this->locale;
            $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $fmt->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
            $fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);

            $formatted = $fmt->format($numeric);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        // Fallback manual según locale.
        [$decSep, $thousandSep] = $this->guessSeparators();

        return number_format($numeric, 0, $decSep, $thousandSep);
    }

    /**
     * Decimal con separador de miles y N decimales (por defecto 2).
     *
     * Ejemplos:
     *  - es-ES: 1.234,50
     *  - en-US: 1,234.50
     *
     * @param  float|string|null $value
     * @param  int               $decimals
     * @param  bool              $nullable  true => null si $value es null, false => 0 formateado
     * @return string|null
     */
    public function decimal($value, int $decimals = 2, bool $nullable = true): ?string
    {
        if ($value === null || $value === '') {
            if ($nullable) {
                return null;
            }

            $value = 0;
        }

        $numeric = (float) $value;

        if (class_exists(\NumberFormatter::class)) {
            $locale = $this->config['number_locale'] ?? $this->locale;
            $fmt = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $fmt->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            $formatted = $fmt->format($numeric);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        [$decSep, $thousandSep] = $this->guessSeparators();

        return number_format($numeric, $decimals, $decSep, $thousandSep);
    }

    /**
     * Atajo para dinero: mismo formateo que decimal, pero opcionalmente con código de moneda.
     *
     * @param  float|string|null $value
     * @param  string            $currency  Ej: 'USD', 'EUR'
     * @param  int               $decimals
     * @param  bool              $withCode  true => "1.234,50 USD"
     * @return string|null
     */
    public function money($value, string $currency = 'USD', int $decimals = 2, bool $withCode = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $numeric = (float) $value;

        if (class_exists(\NumberFormatter::class)) {
            $locale = $this->config['number_locale'] ?? $this->locale;
            $fmt = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            $fmt->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $fmt->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

            $formatted = $fmt->formatCurrency($numeric, $currency);
            if ($formatted !== false) {
                if ($withCode) {
                    // Normalmente ya incluye símbolo; añadimos el código por claridad si se pide.
                    return $formatted . ' ' . $currency;
                }

                return $formatted;
            }
        }

        $base = $this->decimal($numeric, $decimals, false);

        return $withCode ? $base . ' ' . $currency : $base;
    }

    /**
     * Versión "o guion" de decimal (ej: para tablas).
     */
    public function decimalOrDash($value, int $decimals = 2): string
    {
        $formatted = $this->decimal($value, $decimals, true);

        return $formatted === null ? '–' : $formatted;
    }

    /**
     * Convierte distintos tipos a Carbon.
     *
     * @param  CarbonInterface|\DateTimeInterface|int|string|null $value
     * @return CarbonInterface|null
     */
    protected function toCarbon($value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            // Asumimos timestamp unix.
            return Carbon::createFromTimestamp((int) $value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Resuelve el locale base.
     */
    protected function resolveLocale(?string $locale): string
    {
        if ($locale !== null && $locale !== '') {
            return $locale;
        }

        // Si el usuario tiene un campo "locale", lo usamos.
        try {
            if (auth()->check() && ! empty(auth()->user()->locale)) {
                return (string) auth()->user()->locale;
            }
        } catch (\Throwable $e) {
            // Si auth() no está disponible en CLI/queues, ignoramos.
        }

        return (string) app()->getLocale();
    }

    /**
     * Resuelve la config de formato para un locale dado,
     * con fallbacks razonables.
     */
    protected function resolveConfigForLocale(string $locale): array
    {
        $all = config('format.locales', []);

        if (isset($all[$locale])) {
            return $all[$locale];
        }

        $appLocale = config('app.locale');
        if ($appLocale && isset($all[$appLocale])) {
            return $all[$appLocale];
        }

        // Primer elemento de la lista como último recurso.
        if (! empty($all)) {
            return reset($all);
        }

        // Fallback mínimo.
        return [
            'number_locale'   => $locale,
            'date_format'     => 'Y-m-d',
            'time_format'     => 'H:i',
            'datetime_format' => 'Y-m-d H:i',
            'js_date_format'  => 'Y-m-d',
        ];
    }

    /**
     * Deducción simple de separadores decimal y de miles según number_locale.
     *
     * @return array{0:string,1:string} [decimal, miles]
     */
    protected function guessSeparators(): array
    {
        $numberLocale = $this->config['number_locale'] ?? $this->locale;

        // Puedes ampliar este switch según los locales que uses.
        switch ($numberLocale) {
            case 'es-ES':
            case 'es-CL':
            case 'es-AR':
            case 'es':
                return [',', '.']; // decimal, miles

            case 'en-US':
            case 'en':
            default:
                return ['.', ','];
        }
    }
}
