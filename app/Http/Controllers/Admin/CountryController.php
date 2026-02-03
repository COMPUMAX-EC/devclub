<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCountryRequest;
use App\Http\Requests\Admin\UpdateCountryRequest;
use App\Models\Country;
use App\Support\Breadcrumbs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * GET /admin/countries
     */
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            $query = Country::query();

            $search = trim((string) $request->query('search', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone_code', 'like', '%' . $search . '%')
                        ->orWhere('iso2', 'like', '%' . $search . '%')
                        ->orWhere('iso3', 'like', '%' . $search . '%');
                });
            }

            $continent = $request->query('continent');
            if ($continent !== null && $continent !== '') {
                $query->where('continent_code', $continent);
            }

            $status = $request->query('status', 'active');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }

            $countries = $query
                ->orderBy('name') // JSON en TEXT, pero suficiente para ordenar alfabéticamente
                ->get();

            return response()->json([
                'countries'  => $countries
                    ->map(fn (Country $country) => $this->transformCountry($country))
                    ->values(),
                'filters'    => [
                    'search'    => $search,
                    'continent' => $continent,
                    'status'    => $status,
                ],
                'continents' => config('continents.options', config('continents', [])),
            ]);
        }

        Breadcrumbs::add('Países', route('admin.countries.index'));

        return view('admin.countries.index', [
            'continents' => config('continents.options', config('continents', [])),
        ]);
    }

    /**
     * POST /admin/countries
     */
    public function store(StoreCountryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $country = new Country();

        // HasTranslatableJson
        $country->fillTranslations('name', [
            'es' => $data['name']['es'],
            'en' => $data['name']['en'],
        ]);

        $country->iso2           = strtoupper($data['iso2']);
        $country->iso3           = strtoupper($data['iso3']);
        $country->continent_code = $data['continent_code'];
        $country->phone_code     = $data['phone_code'] ?? null;
        $country->is_active      = true;

        $country->save();

        return response()->json([
            'message' => 'País creado correctamente.',
            'data'    => $this->transformCountry($country),
        ], 201);
    }

    /**
     * GET /admin/countries/{country}
     */
    public function show(Country $country): JsonResponse
    {
        return response()->json([
            'data' => $this->transformCountry($country),
        ]);
    }

    /**
     * PUT /admin/countries/{country}
     */
    public function update(UpdateCountryRequest $request, Country $country): JsonResponse
    {
        $data = $request->validated();

        $country->fillTranslations('name', [
            'es' => $data['name']['es'],
            'en' => $data['name']['en'],
        ]);

        $country->iso2           = strtoupper($data['iso2']);
        $country->iso3           = strtoupper($data['iso3']);
        $country->continent_code = $data['continent_code'];
        $country->phone_code     = $data['phone_code'] ?? null;

        $country->save();

        return response()->json([
            'message' => 'País actualizado correctamente.',
            'data'    => $this->transformCountry($country),
        ]);
    }

    /**
     * PUT /admin/countries/{country}/toggle-active
     */
    public function toggleActive(Country $country): JsonResponse
    {
        $country->is_active = ! $country->is_active;
        $country->save();

        return response()->json([
            'message' => $country->is_active
                ? 'País activado correctamente.'
                : 'País desactivado correctamente.',
            'data'    => $this->transformCountry($country),
        ]);
    }

    /**
     * Normaliza país para frontend (Vue).
     */
    protected function transformCountry(Country $country): array
    {
        // Importante: usamos getTranslations del trait para tener el diccionario completo
        $translations = $country->getTranslations('name');
        $translations = array_merge(['es' => null, 'en' => null], $translations);

        $continents = config('continents.options', config('continents', []));

        return [
            'id'              => $country->id,
            'name'            => $translations, // Vue espera un diccionario para usar translate(...)
            'iso2'            => $country->iso2,
            'iso3'            => $country->iso3,
            'continent_code'  => $country->continent_code,
            'continent_label' => $continents[$country->continent_code] ?? $country->continent_code,
            'phone_code'      => $country->phone_code,
            'is_active'       => (bool) $country->is_active,
        ];
    }
}
