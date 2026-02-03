<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Regalia;
use App\Services\RegaliasService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RegaliasController extends Controller
{
    /**
     * Servicio de lógica de Regalías.
     */
    protected RegaliasService $regaliasService;

    public function __construct(RegaliasService $regaliasService)
    {
        $this->regaliasService = $regaliasService;
    }

    /**
     * Vista principal de Regalías (monta <admin-regalias-index>).
     *
     * GET /admin/regalias
     */
    public function index(Request $request)
    {
        // Si más adelante necesitas Policy específica:
        // $this->authorize('viewAny', Regalia::class);

        return view('admin.regalias.index', [
            'title' => __('Regalías'),
        ]);
    }

    /**
     * GET /admin/regalias/api/beneficiaries
     *
     * Lista paginada de beneficiarios de regalías + sus regalías.
     * Estructura:
     *  - data: [
     *      {
     *        beneficiary: { id, display_name, email, status },
     *        regalias: [
     *          {
     *            id, source_type, source_id, beneficiary_user_id, commission,
     *            origin_user: {...} | null,
     *            origin_unit: {...} | null
     *          }, ...
     *        ]
     *      }, ...
     *    ]
     *  - meta.pagination
     *  - meta.regalias_sources (derivado de APP_REGALIAS)
     */
    public function beneficiariesIndex(Request $request)
    {
        $this->ensureCanRead();

        $perPage = $request->input('per_page');
        $q       = $request->input('q');

        $result = $this->regaliasService->getBeneficiariesIndex($perPage, $q);

        return response()->json($result);
    }

    /**
     * GET /admin/regalias/api/beneficiaries/{beneficiary}/origins/users/available
     *
     * Lista usuarios productores disponibles como origen de regalía
     * para un beneficiario dado, con flag is_assigned.
     */
    public function availableOriginsUsers(Request $request, int $beneficiaryId)
    {
        $this->ensureCanEdit();

        $perPage = $request->input('per_page');
        $q       = $request->input('q');
        $status  = $request->input('status'); // active|suspended|locked|null

        $result = $this->regaliasService->getAvailableOriginUsers(
            $beneficiaryId,
            $perPage,
            $q,
            $status
        );

        return response()->json($result);
    }

    /**
     * GET /admin/regalias/api/beneficiaries/{beneficiary}/origins/units/available
     *
     * Lista unidades disponibles como origen de regalía para un beneficiario.
     */
    public function availableOriginsUnits(Request $request, int $beneficiaryId)
    {
        $this->ensureCanEdit();

        $perPage = $request->input('per_page');
        $q       = $request->input('q');
        $status  = $request->input('status'); // active|inactive|all|null

        $result = $this->regaliasService->getAvailableOriginUnits(
            $beneficiaryId,
            $perPage,
            $q,
            $status
        );

        return response()->json($result);
    }

    /**
     * POST /admin/regalias/api/regalias
     *
     * Crea una regalía con comisión = 0 por defecto.
     */
    public function store(Request $request)
    {
        $this->ensureCanEdit();

        $allowedSourceTypes = $this->regaliasService->allowedSourceTypes();

        $data = $request->validate([
            'beneficiary_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('realm', 'admin'),
            ],
            'source_type' => [
                'required',
                'string',
                Rule::in($allowedSourceTypes),
            ],
            'source_id' => ['required', 'integer'],
        ]);

        $beneficiaryId = (int) $data['beneficiary_user_id'];
        $sourceType    = $data['source_type'];
        $sourceId      = (int) $data['source_id'];

        try {
            $regalia = $this->regaliasService->createRegalia(
                $beneficiaryId,
                $sourceType,
                $sourceId
            );
        } catch (DomainException $e) {
            // Errores de negocio -> 422
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data'    => [
                'id'                  => $regalia->id,
                'beneficiary_user_id' => $regalia->beneficiary_user_id,
                'source_type'         => $regalia->source_type,
                'source_id'           => $regalia->source_id,
                'commission'          => (float) $regalia->commission,
            ],
            'message' => 'Regalía creada.',
        ], 201);
    }

    /**
     * PATCH /admin/regalias/api/regalias/{regalia}
     *
     * Actualiza el porcentaje de comisión de una regalía.
     */
    public function update(Request $request, Regalia $regalia)
    {
        $this->ensureCanEdit();

        $data = $request->validate([
            'commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $value = array_key_exists('commission', $data)
            ? (float) $data['commission']
            : null;

        $regalia = $this->regaliasService->updateCommission($regalia, $value);

        return response()->json([
            'data' => [
                'id'                  => $regalia->id,
                'beneficiary_user_id' => $regalia->beneficiary_user_id,
                'source_type'         => $regalia->source_type,
                'source_id'           => $regalia->source_id,
                'commission'          => (float) $regalia->commission,
            ],
            'message' => 'Regalía actualizada.',
        ]);
    }

    /**
     * DELETE /admin/regalias/api/regalias/{regalia}
     */
    public function destroy(Regalia $regalia)
    {
        $this->ensureCanEdit();

        $payload = $this->regaliasService->deleteRegalia($regalia);

        return response()->json([
            'data'    => $payload,
            'message' => 'Regalía eliminada.',
        ]);
    }

    /**
     * Chequea permiso regalia.users.read
     */
    protected function ensureCanRead(): void
    {
        $user = Auth::user();

        if (!$user || !$user->can('regalia.users.read')) {
            abort(403);
        }
    }

    /**
     * Chequea permiso regalia.users.edit
     */
    protected function ensureCanEdit(): void
    {
        $user = Auth::user();

        if (!$user || !$user->can('regalia.users.edit')) {
            abort(403);
        }
    }
}
