<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreZoneRequest;
use App\Http\Requests\Admin\UpdateZoneRequest;
use App\Models\Country;
use App\Models\Zone;
use App\Support\Breadcrumbs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    /**
     * GET /admin/zones
     * - JSON para Vue si la petición lo espera.
     * - Vista Blade si es navegación normal.
     */
    public function index(Request $request)
    {
        if ($request->wantsJson()) {
            $query = Zone::query()
                ->with(['countries']);

            // Ya no hay filtro por "buscar por zona" (nombre), solo estado.
            $status = $request->query('status', 'active');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }

            $zones = $query
                ->orderBy('name')
                ->get();

            $continents = config('continents.options', config('continents', []));

            return response()->json([
                'zones'      => $zones->map(fn (Zone $zone) => $this->transformZone($zone, $continents))->values(),
                'filters'    => [
                    'status' => $status,
                ],
                'continents' => $continents,
            ]);
        }

        Breadcrumbs::add('Zonas', route('admin.zones.index'));

        return view('admin.zones.index', [
            'continents' => config('continents.options', config('continents', [])),
        ]);
    }

    /**
     * POST /admin/zones
     * Crea una zona (siempre activa).
     */
    public function store(StoreZoneRequest $request): JsonResponse
    {
        $data = $request->validated();

        $zone = new Zone();
        $zone->name        = $data['name'];
        $zone->description = $data['description'] ?? null;
        $zone->is_active   = true;
        $zone->save();

        $zone->load('countries');

        $continents = config('continents.options', config('continents', []));

        return response()->json([
            'message' => 'Zona creada correctamente.',
            'data'    => $this->transformZone($zone, $continents),
        ], 201);
    }

    /**
     * GET /admin/zones/{zone}
     * Devuelve datos básicos para modal de edición.
     */
    public function show(Zone $zone): JsonResponse
    {
        return response()->json([
            'data' => [
                'id'          => $zone->id,
                'name'        => $zone->name,
                'description' => $zone->description,
                'is_active'   => (bool) $zone->is_active,
            ],
        ]);
    }

    /**
     * PUT /admin/zones/{zone}
     * Modifica name y description.
     */
    public function update(UpdateZoneRequest $request, Zone $zone): JsonResponse
    {
        $data = $request->validated();

        $zone->name        = $data['name'];
        $zone->description = $data['description'] ?? null;
        $zone->save();

        $zone->load('countries');

        $continents = config('continents.options', config('continents', []));

        return response()->json([
            'message' => 'Zona actualizada correctamente.',
            'data'    => $this->transformZone($zone, $continents),
        ]);
    }

    /**
     * PUT /admin/zones/{zone}/toggle-active
     */
    public function toggleActive(Zone $zone): JsonResponse
    {
        $zone->is_active = ! $zone->is_active;
        $zone->save();

        $zone->load('countries');

        $continents = config('continents.options', config('continents', []));

        return response()->json([
            'message' => $zone->is_active
                ? 'Zona activada correctamente.'
                : 'Zona desactivada correctamente.',
            'data'    => $this->transformZone($zone, $continents),
        ]);
    }

    /**
     * GET /admin/zones/{zone}/countries
     * Países actualmente asociados a la zona.
     */
    public function countries(Zone $zone): JsonResponse
    {
        $zone->load(['countries' => function ($q) {
            $q->orderBy('name');
        }]);

        $continents = config('continents.options', config('continents', []));

        $countries = $zone->countries->map(function (Country $country) use ($continents) {
            return $this->transformCountryForZone($country, $continents);
        })->values();

        return response()->json([
            'zone_id'   => $zone->id,
            'countries' => $countries,
        ]);
    }

    /**
     * GET /admin/zones/{zone}/countries/available
     * Lista de países para el modal "Editar países" con flag attached.
     * - Sin filtro por código telefónico: solo nombre.
     */
    public function availableCountries(Request $request, Zone $zone): JsonResponse
    {
        $zone->load('countries:id');

        $attachedIds = $zone->countries->pluck('id')->all();

        $query = Country::query();

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                // Solo por nombre (campo JSON en TEXT), sin phone_code
                $q->where('name', 'like', '%' . $search . '%');
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
            ->orderBy('name')
            ->get();

        $continents = config('continents.options', config('continents', []));

        $result = $countries->map(function (Country $country) use ($attachedIds, $continents) {
            $data = $this->transformCountryForZone($country, $continents);
            $data['attached'] = in_array($country->id, $attachedIds, true);

            return $data;
        })->values();

        return response()->json([
            'zone_id'    => $zone->id,
            'countries'  => $result,
            'filters'    => [
                'search'    => $search,
                'continent' => $continent,
                'status'    => $status,
            ],
            'continents' => $continents,
        ]);
    }

    /**
     * POST /admin/zones/{zone}/countries/{country}
     * attach país a la zona (idempotente).
     */
    public function attachCountry(Zone $zone, Country $country): JsonResponse
    {
        $zone->countries()->syncWithoutDetaching([$country->id]);

        return response()->json([
            'message' => 'País añadido a la zona.',
        ]);
    }

    /**
     * DELETE /admin/zones/{zone}/countries/{country}
     * detach país de la zona.
     */
    public function detachCountry(Zone $zone, Country $country): JsonResponse
    {
        $zone->countries()->detach($country->id);

        return response()->json([
            'message' => 'País quitado de la zona.',
        ]);
    }

    /**
     * Serializa zona con países para frontend.
     */
    protected function transformZone(Zone $zone, array $continents): array
    {
        $zone->loadMissing(['countries' => function ($q) {
            $q->orderBy('name');
        }]);

        $countries = $zone->countries->map(function (Country $country) use ($continents) {
            return $this->transformCountryForZone($country, $continents);
        })->values();

        return [
            'id'              => $zone->id,
            'name'            => $zone->name,
            'description'     => $zone->description,
            'is_active'       => (bool) $zone->is_active,
            'countries'       => $countries,
            'countries_count' => $countries->count(),
        ];
    }

    /**
     * Serializa país para usarlo dentro del contexto de zonas.
     */
    protected function transformCountryForZone(Country $country, array $continents): array
    {
        $translations = $country->getTranslations('name');
        $translations = array_merge(['es' => null, 'en' => null], $translations);

        return [
            'id'              => $country->id,
            'name'            => $translations,
            'continent_code'  => $country->continent_code,
            'continent_label' => $continents[$country->continent_code] ?? $country->continent_code,
            'phone_code'      => $country->phone_code,
            'is_active'       => (bool) $country->is_active,
        ];
    }
}
