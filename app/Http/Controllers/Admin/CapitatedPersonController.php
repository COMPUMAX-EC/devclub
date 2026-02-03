<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapitatedProductInsured;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapitatedPersonController extends Controller
{
    /**
     * Listado paginado de personas (fichas asegurado por company+product).
     *
     * Puede filtrar por product_id opcionalmente.
     */
    public function index(Company $company, Request $request): JsonResponse
    {
        $productId = $request->query('product_id');

        $query = CapitatedProductInsured::query()
            ->with('product')
            ->where('company_id', $company->id);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        // Respeta el per_page que manda Vue (por defecto 15 si no viene).
        $perPage = (int) $request->query('per_page', 15);
        if ($perPage <= 0) {
            $perPage = 15;
        }

        $persons = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        $payload = [
            'data' => $persons->items(),
            'meta' => [
                'current_page' => $persons->currentPage(),
                'last_page'    => $persons->lastPage(),
                'per_page'     => $persons->perPage(),
                'total'        => $persons->total(),
            ],
        ];

        return response()->json($payload);
    }

    /**
     * Dashboard de persona (para modal).
     *
     * Incluye datos básicos + resumen de contratos y última renovación.
     */
    public function show(Company $company, CapitatedProductInsured $person): JsonResponse
    {
        if ($person->company_id !== $company->id) {
            abort(404);
        }

        $person->load([
            'product',
            'contracts' => function ($q) {
                $q->with(['monthlyRecords' => function ($q2) {
                    $q2->orderByDesc('coverage_month')->limit(6);
                }]);
            },
        ]);

        return response()->json([
            'person'    => $person,
            'contracts' => $person->contracts,
        ]);
    }
}
