<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PlanVersion;
use App\Models\Product;
use App\Models\Zone;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanVersionCountryController extends Controller
{
    /**
     * Listado de países de la versión y datos de apoyo (zonas, continentes).
     */
    public function index(Request $request, Product $product, PlanVersion $planVersion)
    {
        $this->ensureBelongs($product, $planVersion);

        $search        = trim((string) $request->query('search', ''));
        $continentCode = $request->query('continent');

        $countriesQuery = Country::query();

        if ($search !== '') {
            $term = mb_strtolower($search, 'UTF-8');

            $countriesQuery->where(function ($query) use ($term) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', ['%' . $term . '%'])
                    ->orWhereRaw('LOWER(iso2) LIKE ?', ['%' . $term . '%'])
                    ->orWhereRaw('LOWER(iso3) LIKE ?', ['%' . $term . '%']);
            });
        }

        if ($continentCode !== null && $continentCode !== '') {
            $countriesQuery->where('continent_code', $continentCode);
        }

        $countries = $countriesQuery
            ->orderBy('name')
            ->get();

        $attachedCountries = $planVersion->countries()
            ->withPivot('price')
            ->get()
            ->keyBy('id');

        $planCountries = $attachedCountries
            ->values()
            ->map(function (Country $country) {
                $price = $country->pivot ? $country->pivot->price : null;

                return $this->transformPlanCountry($country, $price);
            })
            ->values();

        $allCountries = $countries->map(function (Country $country) use ($attachedCountries) {
            $attached = $attachedCountries->get($country->id);
            $price    = $attached && $attached->pivot ? $attached->pivot->price : null;

            return $this->transformModalCountry($country, $attached !== null, $price);
        })->values();

        $zones = Zone::query()
            ->where('is_active', true)
            ->withCount('countries')
            ->orderBy('name')
            ->get()
            ->map(function (Zone $zone) {
                return [
                    'id'              => $zone->id,
                    'name'            => $zone->name,
                    'countries_count' => (int) $zone->countries_count,
                ];
            })
            ->values();

        $continents = config('continents.options', config('continents', []));

        return response()->json([
            'data' => [
                'plan_countries' => $planCountries,
                'countries'      => $allCountries,
                'zones'          => $zones,
                'continents'     => $continents,
            ],
        ]);
    }

    /**
     * Asocia uno o varios países a la versión (sin precio específico).
     */
    public function store(Request $request, Product $product, PlanVersion $planVersion)
    {
        $this->ensureBelongs($product, $planVersion);

        $ids = collect($request->input('country_ids', []))
            ->filter(function ($id) {
                return $id !== null && $id !== '' && (int) $id > 0;
            })
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'message' => 'No se recibieron países válidos.',
            ], 422);
        }

        $countries = Country::query()
            ->whereIn('id', $ids)
            ->get();

        if ($countries->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron países para asociar.',
            ], 422);
        }

        $existingIds = $planVersion->countries()
            ->whereIn('countries.id', $countries->pluck('id'))
            ->pluck('countries.id');

        $newIds = $countries->pluck('id')->diff($existingIds);

        if ($newIds->isEmpty()) {
            return response()->json([
                'message' => 'Los países seleccionados ya estaban asociados a la versión.',
                'data'    => ['countries' => []],
            ]);
        }

        $attachedCountries = collect();

        DB::transaction(function () use ($planVersion, $newIds, &$attachedCountries) {
            $attachPayload = [];

            foreach ($newIds as $id) {
                $attachPayload[$id] = ['price' => null];
            }

            $planVersion->countries()->attach($attachPayload);

            $attachedCountries = Country::whereIn('id', $newIds)->get();
        });

        $attachedCountries = $attachedCountries->values();

        if ($attachedCountries->isEmpty()) {
            return response()->json([
                'message' => 'No se pudieron asociar los países seleccionados.',
                'data'    => ['countries' => []],
            ], 422);
        }

        Audit::log('plan_version.countries.attach', [
            'plan_version_id' => $planVersion->id,
            'product_id'      => $product->id,
            'country_ids'     => $attachedCountries->pluck('id')->all(),
        ]);

        $result = $attachedCountries->map(function (Country $country) {
            return $this->transformPlanCountry($country, null);
        })->values();

        return response()->json([
            'toast' => [
                'message' => 'Países añadidos correctamente a la versión.',
                'type'    => 'success',
            ],
            'data'  => [
                'countries' => $result,
            ],
        ]);
    }

    /**
     * Asocia todos los países de una zona activa a la versión.
     */
    public function attachZone(Request $request, Product $product, PlanVersion $planVersion)
    {
        $this->ensureBelongs($product, $planVersion);

        $zoneId = (int) $request->input('zone_id');

        if ($zoneId <= 0) {
            return response()->json([
                'message' => 'Zona no válida.',
            ], 422);
        }

        $zone = Zone::query()
            ->where('is_active', true)
            ->find($zoneId);

        if (! $zone) {
            return response()->json([
                'message' => 'Zona no encontrada o inactiva.',
            ], 404);
        }

        $zoneCountryIds = $zone->countries()
            ->pluck('countries.id')
            ->all();

        if (! $zoneCountryIds) {
            return response()->json([
                'message' => 'La zona seleccionada no tiene países asociados.',
            ], 422);
        }

        $alreadyAttachedIds = $planVersion->countries()
            ->whereIn('countries.id', $zoneCountryIds)
            ->pluck('countries.id')
            ->all();

        $attachIds = array_values(array_diff($zoneCountryIds, $alreadyAttachedIds));

        if (! $attachIds) {
            return response()->json([
                'message' => 'Todos los países de la zona ya están asociados a la versión.',
                'data'    => ['countries' => []],
            ]);
        }

        DB::transaction(function () use ($planVersion, $attachIds) {
            $payload = [];

            foreach ($attachIds as $id) {
                $payload[$id] = ['price' => null];
            }

            $planVersion->countries()->attach($payload);
        });

        $countries = Country::whereIn('id', $attachIds)->get();

        Audit::log('plan_version.countries.attach_zone', [
            'plan_version_id' => $planVersion->id,
            'product_id'      => $product->id,
            'zone_id'         => $zone->id,
            'country_ids'     => $countries->pluck('id')->all(),
        ]);

        $result = $countries->map(function (Country $country) {
            return $this->transformPlanCountry($country, null);
        })->values();

        return response()->json([
            'toast' => [
                'message' => 'Zona añadida correctamente a la versión.',
                'type'    => 'success',
            ],
            'data'  => [
                'countries' => $result,
            ],
        ]);
    }

    /**
     * Actualiza el precio específico de un país para la versión.
     */
    public function update(Request $request, Product $product, PlanVersion $planVersion, Country $country)
    {
        $this->ensureBelongs($product, $planVersion);

        $exists = $planVersion->countries()
            ->where('countries.id', $country->id)
            ->exists();

        if (! $exists) {
            abort(404);
        }

        $priceInput = $request->input('price');

        if ($priceInput === '' || $priceInput === null) {
            $price = null;
        } else {
            if (! is_numeric($priceInput) || (float) $priceInput < 0) {
                return response()->json([
                    'message' => 'El precio debe ser un número positivo o vacío.',
                ], 422);
            }

            $price = (float) $priceInput;
        }

        $planVersion->countries()
            ->updateExistingPivot($country->id, [
                'price' => $price,
            ]);

        Audit::log('plan_version.countries.update_price', [
            'plan_version_id' => $planVersion->id,
            'product_id'      => $product->id,
            'country_id'      => $country->id,
            'price'           => $price,
        ]);

        $data = $this->transformPlanCountry($country, $price);

        return response()->json([
            'toast' => [
                'message' => 'Precio por país actualizado correctamente.',
                'type'    => 'success',
            ],
            'data'  => $data,
        ]);
    }

    /**
     * Desasocia un país concreto de la versión.
     */
    public function destroy(Product $product, PlanVersion $planVersion, Country $country)
    {
        $this->ensureBelongs($product, $planVersion);

        $exists = $planVersion->countries()
            ->where('countries.id', $country->id)
            ->exists();

        if (! $exists) {
            return response()->json([
                'message' => 'El país no está asociado a esta versión.',
                'data'    => ['countries' => []],
            ], 404);
        }

        $planVersion->countries()->detach($country->id);

        Audit::log('plan_version.countries.detach', [
            'plan_version_id' => $planVersion->id,
            'product_id'      => $product->id,
            'country_id'      => $country->id,
        ]);

        $data = $this->transformPlanCountry($country, null);

        return response()->json([
            'toast' => [
                'message' => 'País quitado correctamente de la versión.',
                'type'    => 'success',
            ],
            'data'  => [
                'countries' => [$data],
            ],
        ]);
    }

    /**
     * Desasocia todos los países de una zona de la versión.
     */
    public function detachByZone(Request $request, Product $product, PlanVersion $planVersion)
    {
        $this->ensureBelongs($product, $planVersion);

        $zoneId = (int) $request->input('zone_id');

        if ($zoneId <= 0) {
            return response()->json([
                'message' => 'Zona no válida.',
            ], 422);
        }

        $zone = Zone::find($zoneId);

        if (! $zone) {
            return response()->json([
                'message' => 'Zona no encontrada.',
            ], 404);
        }

        $zoneCountryIds = $zone->countries()
            ->pluck('countries.id')
            ->all();

        if (! $zoneCountryIds) {
            return response()->json([
                'message' => 'La zona seleccionada no tiene países asociados.',
            ], 422);
        }

        $attachedIds = $planVersion->countries()
            ->whereIn('countries.id', $zoneCountryIds)
            ->pluck('countries.id')
            ->all();

        if (! $attachedIds) {
            return response()->json([
                'message' => 'Ninguno de los países de la zona está asociado a esta versión.',
                'data'    => ['countries' => []],
            ]);
        }

        $planVersion->countries()->detach($attachedIds);

        $countries = Country::whereIn('id', $attachedIds)->get();

        Audit::log('plan_version.countries.detach_zone', [
            'plan_version_id' => $planVersion->id,
            'product_id'      => $product->id,
            'zone_id'         => $zone->id,
            'country_ids'     => $countries->pluck('id')->all(),
        ]);

        $result = $countries->map(function (Country $country) {
            return $this->transformPlanCountry($country, null);
        })->values();

        return response()->json([
            'toast' => [
                'message' => 'Países de la zona quitados correctamente de la versión.',
                'type'    => 'success',
            ],
            'data'  => [
                'countries' => $result,
            ],
        ]);
    }

    /**
     * Normaliza un país para la lista principal de países del plan.
     */
    protected function transformPlanCountry(Country $country, $price): array
    {
        $data = $this->transformCountry($country);

        $data['price'] = $price !== null ? (float) $price : null;

        return $data;
    }

    /**
     * Normaliza un país para la lista del modal (marcando si está asociado).
     */
    protected function transformModalCountry(Country $country, bool $attached, $price): array
    {
        $data = $this->transformCountry($country);

        $data['attached'] = $attached;
        $data['price']    = $price !== null ? (float) $price : null;

        return $data;
    }

    /**
     * Normaliza un país usando la misma estructura que el listado general de países.
     */
    protected function transformCountry(Country $country): array
    {
        $translations = $country->getTranslations('name');
        $translations = array_merge(['es' => null, 'en' => null], $translations);

        $continents = config('continents.options', config('continents', []));

        return [
            'id'              => $country->id,
            'name'            => $translations,
            'iso2'            => $country->iso2,
            'iso3'            => $country->iso3,
            'continent_code'  => $country->continent_code,
            'continent_label' => $continents[$country->continent_code] ?? $country->continent_code,
            'phone_code'      => $country->phone_code,
            'is_active'       => (bool) $country->is_active,
        ];
    }

    protected function ensureBelongs(Product $product, PlanVersion $planVersion): void
    {
        if ($planVersion->product_id !== $product->id) {
            abort(404);
        }
    }
}
