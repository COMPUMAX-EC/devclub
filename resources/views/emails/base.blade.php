@php
	use Illuminate\Support\Facades\Log;
	$branding = \App\Services\Config\Config::getBrandingWeb();

    $appName   = config('mail.company.name');
    $brandLogo = $branding['logo_email'];
    $siteUrl   = config('app.url');
    $legalName = config('mail.company.legal_name');
    $address   = config('mail.company.address');
    $support   = config('mail.support_address');
@endphp

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title', $appName)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* Estilos simples y seguros para email */
        body{ margin:0; padding:0; font-family:Arial,Helvetica,sans-serif; color:#333; }
        .wrapper{ width:100%; padding:24px 0; background:#f3f4f6; }
        .container{ max-width:640px; margin:0 auto; background:#ffffff; border-radius:8px; border:1px solid #EEE; overflow:hidden; }
        .header{ padding:20px 24px; background:#fafafa; border-bottom:1px solid #eee }
        .brand{ display:flex; align-items:center; gap:12px }
        .brand img{ height:48px }
        .brand h1{ font-size:18px; margin:0; color:#222; font-weight:600 }
        .preheader{ display:none!important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden; }
        .content{ padding:24px }
        h2{ margin:0 0 12px; font-size:20px; color:#222 }
        p{ margin:0 0 12px; line-height:1.55 }
        .summary{ background:#fbfbfd; border:1px solid #eee; border-radius:8px; padding:16px; margin:16px 0 }
        .summary table{ width:100%; border-collapse:collapse }
        .summary td{ padding:6px 0; vertical-align:top }
        .label{ color:#666; width:40% }
        .value{ color:#111 }
        .amount{ font-weight:700; font-size:18px }
        .btn{ display:inline-block; padding:12px 18px; border-radius:6px; text-decoration:none; background:#00a3ff; color:#fff!important; font-weight:600 }
        .btn:hover{ opacity:.92 }
        .note{ font-size:12px; color:#555; }
        .footer{ padding:18px 24px; background:#fafafa; border-top:1px solid #eee; font-size:12px; color:#666 }
        .muted{ color:#777 }
        .divider{ height:1px; background:#eee; margin:16px 0 }
    </style>
</head>
<body>
<!-- preheader (texto de vista previa en la bandeja) -->
<div class="preheader">@yield('preheader')</div>

<div class="wrapper">
    <div class="container">
        <div class="header">
            <div class="brand">
                <img src="{{ $brandLogo }}" alt="{{ $appName }}" />
            </div>
        </div>

        <div class="content">
            @yield('content')
        </div>

        <div class="footer">
            {{ $legalName }} — © {{ date('Y') }}. Todos los derechos reservados.
            <br/>
            Dirección: {{ $address }} · Soporte: <a href="mailto:{{ $support }}">{{ $support }}</a>
            <br/>
            Este correo fue enviado automáticamente. Por favor, no respondas a esta dirección.
        </div>
    </div>
</div>
</body>
</html>
