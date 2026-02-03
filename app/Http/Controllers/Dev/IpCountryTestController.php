<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Services\IpCountry\IpCountryService;
use Illuminate\Http\Request;

/**
 * Este es un controlador para validar el funcionamiento de la geolocalización de IpCountryService
 */
class IpCountryTestController extends Controller
{
    // 1) Vista simple: solo país
    public function country(Request $request, IpCountryService $svc)
    {
        $country = $svc->resolveCountry($request, true);

        return view('dev.ip-country', [
            'country' => $country,
        ]);
    }

    // 2) Vista completa: diagnostics()
    public function diagnostics(Request $request, IpCountryService $svc)
    {
        return view('dev.ip-country-diagnostics', $svc->diagnostics($request));
    }
}
