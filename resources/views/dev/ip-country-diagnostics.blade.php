<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IP Country (Diagnostics)</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, sans-serif; margin: 24px; }
        .card { border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        .row { display:flex; gap:16px; flex-wrap:wrap; }
        .col { flex:1 1 360px; }
        code { background:#f6f6f6; padding:2px 6px; border-radius:6px; }
        .muted { color:#666; }
        .ok { color:#0a7a2f; }
        .bad { color:#a11; }
        .btn { display:inline-block; padding:8px 12px; border-radius:8px; border:1px solid #333; text-decoration:none; color:#111; }
    </style>
</head>
<body>

<h2>IP → País (diagnostics)</h2>
<p class="muted">Ruta: <code>/dev/ip-country/diagnostics</code></p>

@if($refreshRequested)
    <div class="card">
        <strong>Resultado refresh</strong><br>
        <span class="{{ str_starts_with($refreshResult ?? '', 'OK') ? 'ok' : 'bad' }}">
            {{ $refreshResult }}
        </span>
    </div>
@endif

<div class="card">
    <a class="btn" href="{{ route('dev.ip-country.diagnostics', ['refresh' => 1]) }}">Forzar actualización de la base</a>
    &nbsp;|&nbsp;
    <a href="{{ route('dev.ip-country') }}">Ir a simple</a>
</div>

<div class="row">
    <div class="col">
        <div class="card">
            <strong>Config (env)</strong><br><br>

            <div>Provider: <code>{{ $provider }}</code></div>
            <div>TTL provider: <code>{{ $ttlDays }}</code> días</div>

            <hr>

            <div>Fallback ISO2: <code>{{ $fallback_iso2 ?? 'null' }}</code></div>

            <hr>

            <div>IP source: <code>{{ $ip_source }}</code></div>
            <div>Trusted proxies: <code>{{ is_array($trusted_proxies) ? json_encode($trusted_proxies) : $trusted_proxies }}</code></div>

            <div>Header name: <code>{{ $header_name }}</code></div>
            <div>Header precedence: <code>{{ is_array($header_precedence) ? json_encode($header_precedence) : $header_precedence }}</code></div>
            <div>XFF position: <code>{{ $xff_position }}</code></div>
        </div>

        <div class="card">
            <strong>Resolución</strong><br><br>
            <div>REMOTE_ADDR: <code>{{ $remote_addr ?? 'null' }}</code></div>
            <div>Request->ip(): <code>{{ $request_ip ?? 'null' }}</code></div>
            <div>IP efectiva: <code>{{ $effective_ip ?? 'null' }}</code></div>

            <hr>

            <div>ISO2 resuelto: <code>{{ $iso2 ?? 'null' }}</code></div>

            @if($error)
                <div class="bad" style="margin-top:10px;">
                    Error: {{ $error }}
                </div>
            @endif
        </div>
    </div>

    <div class="col">
        <div class="card">
            <strong>Country (solo activos)</strong><br><br>
            @if($countryActive)
                <div>ID: <code>{{ $countryActive->id }}</code></div>
                <div>iso2: <code>{{ $countryActive->iso2 }}</code></div>
                <div>name: <code>{{ json_encode($countryActive->name) }}</code></div>
                <div>is_active: <code>{{ $countryActive->is_active ? 'true' : 'false' }}</code></div>
            @else
                <div class="muted">No se encontró país activo.</div>
            @endif

            <hr>

            <strong>Diagnóstico (incluye inactivos)</strong><br><br>
            @if($countryAny)
                <div>ID: <code>{{ $countryAny->id }}</code></div>
                <div>iso2: <code>{{ $countryAny->iso2 }}</code></div>
                <div>name: <code>{{ json_encode($countryAny->name) }}</code></div>
                <div>is_active: <code>{{ $countryAny->is_active ? 'true' : 'false' }}</code></div>
            @else
                <div class="muted">No existe registro en BD para ese ISO2.</div>
            @endif
        </div>

        <div class="card">
            <strong>MMDB local</strong><br><br>
            <div>Path: <code>{{ $mmdb['path'] }}</code></div>
            <div>Existe: <code>{{ $mmdb['exists'] ? 'true' : 'false' }}</code></div>

            @if($mmdb['exists'])
                <div>Tamaño: <code>{{ number_format($mmdb['size_bytes']) }}</code> bytes</div>
                <div>Modificado: <code>{{ $mmdb['mtime'] ? date('c', $mmdb['mtime']) : 'null' }}</code></div>
                <div>Antigüedad: <code>{{ $mmdb['age_days'] }}</code> días</div>
            @endif

            <hr>

            <strong>Meta</strong><br><br>
            @if($meta)
                <pre style="white-space:pre-wrap; background:#f6f6f6; padding:10px; border-radius:10px;">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            @else
                <div class="muted">No hay meta (se crea al descargar/actualizar).</div>
            @endif
        </div>
    </div>
</div>

</body>
</html>
