<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>@yield('title', config('app.name'))</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    /* Estilos globales de email (inlines-friendly) */
    body{margin:0;padding:0;background:#f5f7fb;font-family:Inter,Arial,sans-serif;color:#1f2937;}
    .container{max-width:640px;margin:0 auto;padding:24px;}
    .card{background:#ffffff;border-radius:10px;padding:28px;box-shadow:0 2px 6px rgba(0,0,0,.06);}
    h1,h2{margin:0 0 12px 0;}
    p{line-height:1.6;margin:0 0 12px 0;}
    .btn{display:inline-block;background:#0d6efd;color:#fff !important;text-decoration:none;padding:12px 18px;border-radius:8px}
    .muted{color:#6b7280;font-size:12px}
    .footer{margin-top:16px;color:#94a3b8;font-size:12px;text-align:center}
    .kbd{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,"Liberation Mono","Courier New",monospace;
         background:#f1f5f9;border-radius:6px;padding:2px 6px;display:inline-block}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      @yield('content')
    </div>
    <div class="footer">
      {{ config('app.name') }} &middot; {{ config('app.url') }}
    </div>
  </div>
</body>
</html>
