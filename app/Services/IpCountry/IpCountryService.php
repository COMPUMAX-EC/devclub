<?php

namespace App\Services\IpCountry;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

class IpCountryService
{
    public function __construct(
        private readonly GeoIpDatabaseManager $db
    ) {}

    /**
     * ÚNICO método que debería usarse desde controladores/casos de uso.
     * Retorna el Country (de tu tabla) según IP -> ISO2.
     * Si no se puede resolver, usa fallback ISO2 desde .env (si está definido).
     */
    public function resolveCountry(Request $request, bool $onlyActive = true): ?Country
    {
        $ip = $this->getClientIp($request);

        $iso2 = null;

        // Intento principal: IP pública -> GeoIP -> ISO2
        if ($this->isValidPublicIp($ip)) {
            $iso2 = $this->resolveIso2ByIp($ip);
        }

        // Fallback: ISO2 definido en .env
        if (!$iso2) {
            $iso2 = $this->fallbackIso2();
        }

        if (!$iso2) {
            return null;
        }

        $iso2 = strtoupper($iso2);

        $q = Country::query()->where('iso2', $iso2);
        if ($onlyActive) {
            $q->where('is_active', true);
        }

        return $q->first();
    }

    /**
     * Si en algún punto te sirve recuperar solo ISO2 (opcional).
     */
    public function resolveIso2(Request $request): ?string
    {
        $ip = $this->getClientIp($request);
        if (!$this->isValidPublicIp($ip)) {
            return $this->fallbackIso2();
        }

        return $this->resolveIso2ByIp($ip) ?? $this->fallbackIso2();
    }

    /**
     * Resuelve ISO2 por IP usando MMDB (con auto-descarga/refresh).
     */
    public function resolveIso2ByIp(string $ip): ?string
    {
        $ip = trim($ip);

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        $ttl = (int) config('ip_country.cache_ttl_seconds', 86400);
        $key = 'ip_country:iso2:' . sha1($ip);

        return Cache::remember($key, $ttl, function () use ($ip) {
            try {
                $reader = $this->db->getReader();
                $data = $reader->get($ip);

                if (!is_array($data)) {
                    return null;
                }

                // MaxMind-like
                $iso2 = data_get($data, 'country.iso_code');

                // IPLocate-like
                if (!$iso2) {
                    $iso2 = data_get($data, 'country_code');
                }

                $iso2 = $iso2 ? strtoupper((string) $iso2) : null;

                return $iso2 ?: null;

            } catch (\Throwable) {
                return null;
            }
        });
    }

    public function forceUpdateDatabase(): void
    {
        $this->db->ensureFreshDatabase(true);
    }

    /**
     * Determina la IP del cliente según config/env.
     */
    private function getClientIp(Request $request): ?string
    {
        $source = (string) config('ip_country.ip.source', 'request_ip');

        // Caso directo: sin proxies, lo más común en tu escenario.
        if ($source === 'request_ip') {
            return $request->ip();
        }

        if ($source === 'remote_addr') {
            return $request->server('REMOTE_ADDR');
        }

        // A partir de aquí: headers. Solo se aceptan si el request viene de proxy confiable.
        $remoteAddr = $request->server('REMOTE_ADDR');
        $trusted = (array) config('ip_country.ip.trusted_proxies', []);

        $headersAllowed = $this->isFromTrustedProxy($remoteAddr, $trusted);

        if (!$headersAllowed) {
            // Si no confías en proxies, ignora headers para evitar spoofing.
            return $remoteAddr ?: $request->ip();
        }

        if ($source === 'cloudflare') {
            return $this->extractSingleHeaderIp($request->headers->get('CF-Connecting-IP'));
        }

        if ($source === 'header') {
            $headerName = (string) config('ip_country.ip.header_name', 'X-Forwarded-For');
            $value = $request->headers->get($headerName);

            if (strcasecmp($headerName, 'X-Forwarded-For') === 0) {
                return $this->extractXForwardedForIp($value);
            }

            return $this->extractSingleHeaderIp($value);
        }

        if ($source === 'auto') {
            $precedence = (array) config('ip_country.ip.header_precedence', []);

            foreach ($precedence as $h) {
                $value = $request->headers->get($h);
                if (!$value) {
                    continue;
                }

                if (strcasecmp($h, 'X-Forwarded-For') === 0) {
                    $ip = $this->extractXForwardedForIp($value);
                } else {
                    $ip = $this->extractSingleHeaderIp($value);
                }

                if ($ip) {
                    return $ip;
                }
            }

            return $remoteAddr ?: $request->ip();
        }

        // Fallback defensivo
        return $request->ip();
    }

    private function extractSingleHeaderIp($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        // Si viene lista accidentalmente, tomar primer elemento
        if (str_contains($value, ',')) {
            $value = trim(explode(',', $value)[0]);
        }

        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }

    private function extractXForwardedForIp($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $value))));
        if (!$parts) {
            return null;
        }

        $pos = (string) config('ip_country.ip.xff_position', 'first');
        $candidate = ($pos === 'last') ? end($parts) : $parts[0];

        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : null;
    }

    private function isValidPublicIp(?string $ip): bool
    {
        if (!$ip) return false;

        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    private function fallbackIso2(): ?string
    {
        $iso2 = config('ip_country.fallback_iso2');

        if (!is_string($iso2)) {
            return null;
        }

        $iso2 = strtoupper(trim($iso2));

        // ISO2 debe ser exactamente 2 letras
        return preg_match('/^[A-Z]{2}$/', $iso2) ? $iso2 : null;
    }

    private function isFromTrustedProxy(?string $remoteAddr, array $trustedProxies): bool
    {
        if (!$remoteAddr || !filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return false;
        }

        $trustedProxies = array_values(array_filter($trustedProxies));
        if (!$trustedProxies) {
            return false;
        }

        // IpUtils soporta IPs y CIDRs
        return IpUtils::checkIp($remoteAddr, $trustedProxies);
    }
	
    /**
     * SOLO PARA PRUEBAS/DEV: te muestra la IP efectiva según config/env.
     */
    public function resolveEffectiveIp(Request $request): ?string
    {
        return $this->getClientIp($request);
    }

    /**
     * SOLO PARA PRUEBAS/DEV: payload completo para la vista diagnostics.
     * Mantiene la idea "como lo teníamos antes".
     */
    public function diagnostics(Request $request): array
    {
        $provider = config('ip_country.default');

        $refreshRequested = (bool) $request->boolean('refresh');
        $refreshResult = null;

        if ($refreshRequested) {
            try {
                $this->forceUpdateDatabase();
                $refreshResult = 'OK: base actualizada/descargada.';
            } catch (\Throwable $e) {
                $refreshResult = 'ERROR: ' . $e->getMessage();
            }
        }

        $remoteAddr = $request->server('REMOTE_ADDR');
        $requestIp = $request->ip();

        $effectiveIp = null;
        $iso2 = null;
        $error = null;

        try {
            $effectiveIp = $this->getClientIp($request);
            $iso2 = $effectiveIp ? $this->resolveIso2ByIp($effectiveIp) : null;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $countryActive = $this->resolveCountry($request, true);

        $countryAny = null;
        if ($iso2) {
            $countryAny = Country::query()
                ->where('iso2', strtoupper($iso2))
                ->first();
        }

        $ttlDays = (int) (config("ip_country.providers.$provider.ttl_days") ?? 0);

        return [
            'provider' => $provider,
            'ttlDays' => $ttlDays,

            'fallback_iso2' => config('ip_country.fallback_iso2'),
            'ip_source' => config('ip_country.ip.source'),
            'trusted_proxies' => config('ip_country.ip.trusted_proxies'),
            'header_name' => config('ip_country.ip.header_name'),
            'header_precedence' => config('ip_country.ip.header_precedence'),
            'xff_position' => config('ip_country.ip.xff_position'),

            'refreshRequested' => $refreshRequested,
            'refreshResult' => $refreshResult,

            'remote_addr' => $remoteAddr,
            'request_ip' => $requestIp,
            'effective_ip' => $effectiveIp,

            'iso2' => $iso2,
            'error' => $error,

            'countryActive' => $countryActive,
            'countryAny' => $countryAny,

            'mmdb' => $this->mmdbInfo(),
            'meta' => $this->mmdbMeta(),
        ];
    }

    private function mmdbInfo(): array
    {
        $storageDir = rtrim(config('ip_country.storage_dir'), DIRECTORY_SEPARATOR);
        $mmdbPath = $storageDir . DIRECTORY_SEPARATOR . 'ip-country.mmdb';

        $exists = is_file($mmdbPath);
        $mtime = $exists ? filemtime($mmdbPath) : null;

        return [
            'path' => $mmdbPath,
            'exists' => $exists,
            'size_bytes' => $exists ? filesize($mmdbPath) : null,
            'mtime' => $mtime,
            'age_days' => $mtime ? round((time() - $mtime) / 86400, 2) : null,
        ];
    }

    private function mmdbMeta(): ?array
    {
        $storageDir = rtrim(config('ip_country.storage_dir'), DIRECTORY_SEPARATOR);
        $metaPath = $storageDir . DIRECTORY_SEPARATOR . 'ip-country.meta.json';

        if (!is_file($metaPath)) {
            return null;
        }

        $raw = file_get_contents($metaPath);
        $json = json_decode($raw, true);

        return is_array($json) ? $json : null;
    }
}
