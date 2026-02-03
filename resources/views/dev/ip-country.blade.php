<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IP Country (Simple)</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, sans-serif; margin: 24px; }
        .card { border: 1px solid #ddd; border-radius: 10px; padding: 16px; margin-bottom: 16px; }
        code { background: #f6f6f6; padding: 2px 6px; border-radius: 6px; }
        .muted { color:#666; }
    </style>
</head>
<body>
    <h2>Detección de país (simple)</h2>
    <p class="muted">Ruta: <code>/dev/ip-country</code></p>

    <div class="card">
        @if($country)
            <div><strong>Country encontrado</strong></div>
            <div>ID: <code>{{ $country->id }}</code></div>
            <div>iso2: <code>{{ $country->iso2 }}</code></div>
            <div>name: <code>{{ json_encode($country->name) }}</code></div>
            <div>is_active: <code>{{ $country->is_active ? 'true' : 'false' }}</code></div>
        @else
            <div><strong>No se pudo determinar un país</strong></div>
            <div class="muted">Revisa el fallback (<code>IP_COUNTRY_FALLBACK_ISO2</code>) y que exista en tu tabla <code>countries</code>.</div>
        @endif
    </div>

    <div class="card">
        <a href="{{ route('dev.ip-country.diagnostics') }}">Ir a diagnostics</a>
    </div>
</body>
</html>
