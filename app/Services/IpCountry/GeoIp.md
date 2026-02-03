# IP → País (Laravel) — Manual de uso, configuración y ejemplo (1 solo bloque)

## Qué hace
Resuelve el país del cliente a partir de la IP del request y devuelve un registro `Country` desde tu tabla `countries`. Internamente:
- Obtiene la IP efectiva según configuración.
- Usa una base GeoIP local (MMDB) con descarga/refresh automático por TTL.
- Aplica fallback ISO-2 si no se puede resolver por IP.
- Busca el `Country` por `iso2` (y opcionalmente solo activos).

## Requisitos
- Paquete MMDB: composer require geoip2/geoip2
- Permisos de escritura en: storage/app/ip-country/
- Tabla countries poblada con iso2 (ISO-2) y, si aplica, is_active.

---

## Configuración detallada (.env)

### 1) Proveedor GeoIP y refresco del archivo (MMDB)
- IP_COUNTRY_PROVIDER
  - Proveedor de base local.
  - Ej: iplocate
- IP_COUNTRY_TTL_DAYS_IPLOCATE
  - Días máximos de validez del archivo local antes de re-descargar (solo aplica si el provider es iplocate).
- IP_COUNTRY_CACHE_TTL_SECONDS
  - Tiempo de caché para IP→ISO2.

### 2) URL de descarga (IPLocate) — IMPORTANTE
- IP_COUNTRY_IPLOCATE_URL
  - Debe apuntar al binario MMDB real (no a un “pointer” de Git LFS).
  - Recomendado:
    - https://cdn.jsdelivr.net/npm/@ip-location-db/iplocate-country-mmdb/iplocate-country.mmdb
  - Alternativa:
    - https://fastly.jsdelivr.net/npm/@ip-location-db/iplocate-country-mmdb/iplocate-country.mmdb

### 3) Fallback ISO-2
- IP_COUNTRY_FALLBACK_ISO2
  - ISO-2 de 2 letras (CL, CO, US, etc.).
  - Si está vacío o inválido, el fallback se considera null.

### 4) Cómo obtener la IP (IP_COUNTRY_IP_SOURCE)
- request_ip: usa $request->ip() (Laravel).
- remote_addr: usa REMOTE_ADDR (recomendado sin proxy).
- header: usa un header específico (requiere trusted proxies).
- cloudflare: usa CF-Connecting-IP (requiere trusted proxies).
- auto: prueba headers en orden y cae a REMOTE_ADDR (requiere trusted proxies).

Variables auxiliares:
- IP_COUNTRY_IP_HEADER_NAME (solo source=header)
- IP_COUNTRY_IP_HEADER_PRECEDENCE (solo source=auto)
- IP_COUNTRY_XFF_POSITION (first|last) para X-Forwarded-For
- IP_COUNTRY_TRUSTED_PROXIES (CSV IP/CIDR confiables)

Regla de seguridad clave:
- Si source=header/auto/cloudflare, el servicio SOLO usa headers cuando REMOTE_ADDR está dentro de IP_COUNTRY_TRUSTED_PROXIES. Si no, ignora headers y cae a REMOTE_ADDR/request_ip.

### 5) Setups típicos (copiar/pegar)

A) Plesk sin proxy reverso
IP_COUNTRY_PROVIDER=iplocate
IP_COUNTRY_IPLOCATE_URL=https://cdn.jsdelivr.net/npm/@ip-location-db/iplocate-country-mmdb/iplocate-country.mmdb
IP_COUNTRY_TTL_DAYS_IPLOCATE=7
IP_COUNTRY_CACHE_TTL_SECONDS=86400
IP_COUNTRY_FALLBACK_ISO2=CL
IP_COUNTRY_IP_SOURCE=remote_addr
IP_COUNTRY_TRUSTED_PROXIES=

B) Auto con reverse proxy/balanceador (solo si corresponde)
IP_COUNTRY_PROVIDER=iplocate
IP_COUNTRY_IPLOCATE_URL=https://cdn.jsdelivr.net/npm/@ip-location-db/iplocate-country-mmdb/iplocate-country.mmdb
IP_COUNTRY_TTL_DAYS_IPLOCATE=7
IP_COUNTRY_CACHE_TTL_SECONDS=86400
IP_COUNTRY_FALLBACK_ISO2=CL
IP_COUNTRY_IP_SOURCE=auto
IP_COUNTRY_TRUSTED_PROXIES=10.0.0.0/8,127.0.0.1/32
IP_COUNTRY_IP_HEADER_PRECEDENCE=CF-Connecting-IP,X-Forwarded-For,X-Real-IP
IP_COUNTRY_XFF_POSITION=first

---

## Configuración — config/ip_country.php

Este archivo consolida los valores del `.env` y define:
- Proveedor activo y TTL.
- Dónde se guarda el MMDB local.
- Fallback ISO-2.
- Estrategia de obtención de IP (fuente y reglas de headers).
- Catálogo de proveedores soportados (cada uno con su tipo de descarga/parseo).

### Estructura y llaves principales

1) default
- Origen: IP_COUNTRY_PROVIDER
- Define qué proveedor dentro de providers se usa como “activo”.
- Ejemplo: 'iplocate'

2) cache_ttl_seconds
- Origen: IP_COUNTRY_CACHE_TTL_SECONDS
- TTL del caché para IP→ISO2.

3) storage_dir
- Ruta donde se guarda el archivo MMDB local y el meta:
  - storage/app/ip-country/ip-country.mmdb
  - storage/app/ip-country/ip-country.meta.json

4) fallback_iso2
- Origen: IP_COUNTRY_FALLBACK_ISO2
- ISO-2 de fallback a usar cuando no hay ISO-2 por IP.

5) ip (bloque de resolución de IP)
- ip.source
  - Origen: IP_COUNTRY_IP_SOURCE
  - request_ip | remote_addr | header | cloudflare | auto
- ip.header_name
  - Origen: IP_COUNTRY_IP_HEADER_NAME
  - Usado solo si source=header
- ip.header_precedence
  - Origen: IP_COUNTRY_IP_HEADER_PRECEDENCE (CSV)
  - Usado solo si source=auto
  - Es convertido a array (split por coma + trim)
- ip.xff_position
  - Origen: IP_COUNTRY_XFF_POSITION
  - first | last
  - Usado cuando se interpreta X-Forwarded-For con múltiples IPs
- ip.trusted_proxies
  - Origen: IP_COUNTRY_TRUSTED_PROXIES (CSV)
  - Se convierte a array de strings IP/CIDR
  - Determina si se puede confiar en headers (seguridad anti-spoof)

6) providers (catálogo de proveedores)
Cada proveedor define:
- type: cómo descargar/descomprimir/extraer el MMDB
- ttl_days: validez máxima del archivo local para ese proveedor
- parámetros específicos según type

### Provider: iplocate
- type: mmdb
- ttl_days: IP_COUNTRY_TTL_DAYS_IPLOCATE
- download_url: IP_COUNTRY_IPLOCATE_URL
- Descarga directa del MMDB (sin compresión).

### Provider: dbip_lite (si lo habilitas)
- type: mmdb_gz_discover
- ttl_days: IP_COUNTRY_TTL_DAYS_DBIP
- discover_page_url: IP_COUNTRY_DBIP_PAGE
- discover_regex: regex para encontrar el link .mmdb.gz en la página
- http_timeout_seconds: IP_COUNTRY_DBIP_TIMEOUT
- Flujo:
  1) baja HTML de la página
  2) extrae URL del .mmdb.gz por regex
  3) descarga .gz
  4) gunzip → .mmdb
  5) reemplazo atómico

### Provider: ip2location_lite (si lo habilitas)
- type: zip_mmdb
- ttl_days: IP_COUNTRY_TTL_DAYS_IP2LOCATION
- token: IP2LOCATION_DOWNLOAD_TOKEN
- file_code: IP2LOCATION_FILE_CODE (ej: DB1LITEMMDB)
- download_url_template: template con {token} y {file}
- http_timeout_seconds: IP_COUNTRY_IP2LOCATION_TIMEOUT
- Flujo:
  1) arma URL con token + file_code
  2) descarga ZIP
  3) extrae el primer .mmdb dentro del ZIP
  4) reemplazo atómico

### Provider: maxmind_geolite2 (si lo habilitas)
- type: archive_mmdb
- ttl_days: IP_COUNTRY_TTL_DAYS_MAXMIND
- download_url: MAXMIND_DOWNLOAD_URL
- account_id / license_key: MAXMIND_ACCOUNT_ID / MAXMIND_LICENSE_KEY (si aplica basic auth)
- archive_format: MAXMIND_ARCHIVE_FORMAT (zip o tar.gz)
- http_timeout_seconds: IP_COUNTRY_MAXMIND_TIMEOUT
- Flujo:
  1) descarga archivo comprimido
  2) extrae el primer .mmdb dentro del archivo
  3) reemplazo atómico

---

## Uso (API del servicio)

### Método principal
resolveCountry(Request $request, bool $onlyActive = true): ?Country

Flujo interno:
1) Determina IP efectiva según ip.source y trusted proxies.
2) Si la IP es pública, resuelve ISO-2 desde MMDB (auto-descarga/refresh si falta o venció por ttl_days del provider).
3) Si no hay ISO-2, usa fallback_iso2.
4) Busca Country por iso2 (y is_active=true si onlyActive=true).
5) Devuelve Country o null.

---

## Ejemplo de uso (mínimo)

En un controlador (inyección por DI):
- $country = $ipCountryService->resolveCountry($request, true);

Ejemplo de decisión:
- if ($country && $country->iso2 === 'CL') { ... }

---

## Notas rápidas de troubleshooting
- Error “Archivo descargado inválido o vacío”: URL incorrecta (Git LFS pointer) o bloqueo de salida HTTP.
- IP efectiva 127.0.0.1: loopback, lookup real no aplica → cae a fallback.
- ISO-2 resuelto pero Country null: falta el iso2 en countries o está inactivo y estás filtrando.
