<?php

namespace App\Services;

use App\Models\BusinessUnit;
use App\Models\Regalia;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Lógica de dominio para Regalías.
 *
 * Esta clase concentra:
 *  - Tipos de origen permitidos según configuración APP_REGALIAS.
 *  - Listados y estructuras para endpoints (beneficiarios y orígenes).
 *  - Creación, actualización y eliminación de Regalías.
 *  - Validación de ciclos usuario-usuario.
 *  - Validación de redundancias cíclicas en jerarquía de unidades.
 */
class RegaliasService
{
    /**
     * Tipos soportados por el backend hoy.
     * Si en el futuro agregas otro tipo, se debe incluir aquí.
     */
    private const SUPPORTED_SOURCE_TYPES = ['user', 'unit'];

    /**
     * Tipos de origen permitidos según APP_REGALIAS.
     *
     * - Si APP_REGALIAS viene vacío => []
     * - Si viene no-vacío pero no se obtiene ningún tipo => 500
     * - Si contiene algún tipo inválido/no soportado => 500
     */
    public function allowedSourceTypes(): array
    {
        $raw = env('APP_REGALIAS');

        // Caso 1: no configurado o solo espacios -> array vacío (sin error explícito)
        if ($raw === null || trim((string) $raw) === '') {
            return [];
        }

        $rawString = (string) $raw;

        $parts = array_map('trim', explode(',', $rawString));
        $parts = array_filter($parts, static fn ($p) => $p !== '');

        // Caso 2: cadena no vacía pero sin tipos válidos tras el split => mal formateado
        if (empty($parts)) {
            abort(500, 'APP_REGALIAS está mal formateado (no se encontraron tipos de origen).');
        }

        $normalized = [];

        foreach ($parts as $p) {
            $lower = strtolower($p);

            // Validación básica de formato del tipo
            if (!preg_match('/^[a-z0-9_-]+$/', $lower)) {
                abort(500, 'APP_REGALIAS contiene un tipo de origen inválido: ' . $p);
            }

            // Validación contra tipos soportados por el backend
            if (!in_array($lower, self::SUPPORTED_SOURCE_TYPES, true)) {
                abort(500, 'APP_REGALIAS contiene un tipo de origen no soportado por el backend: ' . $p);
            }

            $normalized[] = $lower;
        }

        // Por seguridad: si después de normalizar quedara vacío, también es error de config
        if (empty($normalized)) {
            abort(500, 'APP_REGALIAS no contiene tipos de origen soportados.');
        }

        // Normalizamos duplicados
        return array_values(array_unique($normalized));
    }

    /**
     * Lista de tipos de origen a exponer al front.
     */
    public function regaliasSources(): array
    {
        return $this->allowedSourceTypes();
    }

    /**
     * Normaliza el per_page para paginaciones (límites de seguridad).
     */
    protected function normalizePerPage($perPage): int
    {
        $perPage = (int) ($perPage ?? 20);

        if ($perPage <= 0) {
            return 20;
        }

        if ($perPage > 100) {
            return 100;
        }

        return $perPage;
    }

    /**
     * Crea el bloque meta.pagination a partir de un paginator.
     */
    protected function buildPaginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
            'from'         => $paginator->firstItem() ?? 0,
            'to'           => $paginator->lastItem() ?? 0,
        ];
    }

    /**
     * Lógica de /admin/regalias/api/beneficiaries
     *
     * Retorna:
     *  [
     *    'data' => [...],
     *    'meta' => [
     *      'pagination' => [...],
     *      'regalias_sources' => [...]
     *    ]
     *  ]
     */
    public function getBeneficiariesIndex($perPageInput, ?string $search): array
    {
        $perPage = $this->normalizePerPage($perPageInput);
        $q = trim((string) ($search ?? ''));

        // Usuarios que tienen al menos una regalía como beneficiario
        $beneficiariesQuery = User::query()
            ->where('realm', 'admin')
            ->whereIn('id', function ($sub) {
                $sub->select('beneficiary_user_id')->from('regalias');
            });

        if ($q !== '') {
            $beneficiariesQuery->where(function ($qq) use ($q) {
                $qq->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('display_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $paginator = $beneficiariesQuery
            ->orderBy('display_name')
            ->orderBy('id')
            ->paginate($perPage);

        $beneficiaries = collect($paginator->items());
        $paginationMeta = $this->buildPaginationMeta($paginator);

        if ($beneficiaries->isEmpty()) {
            return [
                'data' => [],
                'meta' => [
                    'pagination'      => $paginationMeta,
                    'regalias_sources' => $this->regaliasSources(),
                ],
            ];
        }

        $beneficiaryIds = $beneficiaries->pluck('id')->all();

        // Todas las regalías de esos beneficiarios
        $regalias = Regalia::query()
            ->whereIn('beneficiary_user_id', $beneficiaryIds)
            ->get();

        // Orígenes usuarios
        $userOriginIds = $regalias
            ->where('source_type', 'user')
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->all();

        $originUsers = $userOriginIds
            ? User::query()->whereIn('id', $userOriginIds)->get()->keyBy('id')
            : collect();

        // Orígenes unidades (con memberships para poder construir nombre efectivo)
        $unitOriginIds = $regalias
            ->where('source_type', 'unit')
            ->pluck('source_id')
            ->filter()
            ->unique()
            ->all();

        $originUnits = $unitOriginIds
            ? BusinessUnit::query()
                ->with(['memberships.user'])
                ->whereIn('id', $unitOriginIds)
                ->get()
                ->keyBy('id')
            : collect();

        $data = $beneficiaries->map(function (User $beneficiary) use ($regalias, $originUsers, $originUnits) {
            $rows = $regalias
                ->where('beneficiary_user_id', $beneficiary->id)
                ->values()
                ->map(function (Regalia $reg) use ($originUsers, $originUnits) {
                    $originUser = null;
                    $originUnit = null;

                    if ($reg->source_type === 'user') {
                        $u = $originUsers->get($reg->source_id);
                        if ($u) {
                            $originUser = [
                                'id'           => $u->id,
                                'display_name' => $u->display_name,
                                'email'        => $u->email,
                                'status'       => $u->status,
                            ];
                        }
                    } elseif ($reg->source_type === 'unit') {
                        $u = $originUnits->get($reg->source_id);
                        if ($u) {
                            $originUnit = [
                                'id'     => $u->id,
                                // nombre efectivo (freelance => fullname único usuario)
                                'name'   => $u->displayName(),
                                'status' => $u->status,
                                'type'   => $u->type,
                            ];
                        }
                    }

                    return [
                        'id'                   => $reg->id,
                        'source_type'          => $reg->source_type,
                        'source_id'            => $reg->source_id,
                        'beneficiary_user_id'  => $reg->beneficiary_user_id,
                        'commission'           => (float) $reg->commission,
                        'origin_user'          => $originUser,
                        'origin_unit'          => $originUnit,
                    ];
                })
                ->all();

            return [
                'beneficiary' => [
                    'id'           => $beneficiary->id,
                    'display_name' => $beneficiary->display_name,
                    'email'        => $beneficiary->email,
                    'status'       => $beneficiary->status,
                ],
                'regalias' => $rows,
            ];
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'pagination'      => $paginationMeta,
                'regalias_sources' => $this->regaliasSources(),
            ],
        ];
    }

    /**
     * Lógica de /admin/regalias/api/beneficiaries/{beneficiary}/origins/users/available
     *
     * Retorna:
     *  [
     *    'data' => [...],
     *    'meta' => ['pagination' => [...]]
     *  ]
     */
    public function getAvailableOriginUsers(
        int $beneficiaryId,
        $perPageInput,
        ?string $search,
        ?string $status
    ): array {
        $perPage = $this->normalizePerPage($perPageInput);
        $q = trim((string) ($search ?? ''));

        // Aseguramos que el beneficiario exista
        $beneficiary = User::query()
            ->where('realm', 'admin')
            ->findOrFail($beneficiaryId);

        $query = User::query()
            ->where('realm', 'admin');

        if ($status) {
            $query->where('status', $status);
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('display_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $paginator = $query
            ->orderBy('display_name')
            ->orderBy('id')
            ->paginate($perPage);

        $users = collect($paginator->items());
        $paginationMeta = $this->buildPaginationMeta($paginator);

        $userIds = $users->pluck('id')->all();

        // Regalías existentes para este beneficiario con origen user en esta página
        $existing = Regalia::query()
            ->where('beneficiary_user_id', $beneficiary->id)
            ->where('source_type', 'user')
            ->whereIn('source_id', $userIds)
            ->get()
            ->keyBy('source_id');

        $rows = $users->map(function (User $user) use ($existing) {
            $reg = $existing->get($user->id);

            return [
                'id'           => $user->id,
                'display_name' => $user->display_name,
                'email'        => $user->email,
                'status'       => $user->status,
                'is_assigned'  => $reg !== null,
                'regalia_id'   => $reg ? $reg->id : null,
                'commission'   => $reg ? (float) $reg->commission : null,
            ];
        })->values();

        return [
            'data' => $rows,
            'meta' => [
                'pagination' => $paginationMeta,
            ],
        ];
    }

    /**
     * Lógica de /admin/regalias/api/beneficiaries/{beneficiary}/origins/units/available
     *
     * Retorna:
     *  [
     *    'data' => [...],
     *    'meta' => ['pagination' => [...]]
     *  ]
     */
    public function getAvailableOriginUnits(
        int $beneficiaryId,
        $perPageInput,
        ?string $search,
        ?string $status
    ): array {
        $perPage = $this->normalizePerPage($perPageInput);
        $q = trim((string) ($search ?? ''));

        // Aseguramos que el beneficiario exista
        $beneficiary = User::query()
            ->where('realm', 'admin')
            ->findOrFail($beneficiaryId);

        $query = BusinessUnit::query()
            ->with(['memberships.user']);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($q !== '') {
            // Búsqueda por:
            // - nombre de la unidad
            // - nombre del usuario miembro (display_name / first_name / last_name)
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhereHas('memberships.user', function ($userQ) use ($q) {
                        $userQ->where('display_name', 'like', "%{$q}%")
                            ->orWhere('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%");
                    });
            });
        }

        $paginator = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);

        $units   = collect($paginator->items());
        $paginationMeta = $this->buildPaginationMeta($paginator);
        $unitIds = $units->pluck('id')->all();

        // Regalías existentes para este beneficiario con origen unit en esta página
        $existing = Regalia::query()
            ->where('beneficiary_user_id', $beneficiary->id)
            ->where('source_type', 'unit')
            ->whereIn('source_id', $unitIds)
            ->get()
            ->keyBy('source_id');

        $rows = $units->map(function (BusinessUnit $unit) use ($existing) {
            $reg = $existing->get($unit->id);

            return [
                'id'          => $unit->id,
                // nombre efectivo (freelance => fullname único usuario)
                'name'        => $unit->displayName(),
                'status'      => $unit->status,
                'type'        => $unit->type,
                'is_assigned' => $reg !== null,
                'regalia_id'  => $reg ? $reg->id : null,
                'commission'  => $reg ? (float) $reg->commission : null,
            ];
        })->values();

        return [
            'data' => $rows,
            'meta' => [
                'pagination' => $paginationMeta,
            ],
        ];
    }

    /**
     * Crea una Regalía, incluyendo validaciones de:
     *  - existencia de origen
     *  - duplicados
     *  - ciclos usuario-usuario
     *  - redundancias cíclicas de unidades
     *
     * Lanza DomainException para errores de negocio (422 en capa HTTP).
     */
    public function createRegalia(int $beneficiaryId, string $sourceType, int $sourceId): Regalia
    {
        // Validar existencia del origen según tipo
        switch ($sourceType) {
            case 'user':
                User::query()
                    ->where('realm', 'admin')
                    ->findOrFail($sourceId);
                break;

            case 'unit':
                BusinessUnit::query()
                    ->findOrFail($sourceId);
                break;

            default:
                // No debería llegar aquí si la capa HTTP usa allowedSourceTypes()
                abort(500, 'Tipo de origen de regalía no soportado por el backend.');
        }

        // Evitar duplicados (además del unique en BD)
        $exists = Regalia::query()
            ->where('beneficiary_user_id', $beneficiaryId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();

        if ($exists) {
            throw new DomainException('Ya existe una regalía para este beneficiario y origen.');
        }

        // Validar ciclos en relaciones usuario-usuario:
        if ($sourceType === 'user') {
            if ($this->wouldCreateUserCycle($beneficiaryId, $sourceId)) {
                throw new DomainException('La relación de regalías entre usuarios genera un ciclo y no es válida.');
            }
        }

        // Validar redundancias cíclicas en relaciones de unidades:
        if ($sourceType === 'unit') {
            if ($this->wouldCreateUnitRedundancyCycle($beneficiaryId, $sourceId)) {
                throw new DomainException('La unidad seleccionada genera una redundancia en la jerarquía de unidades para este beneficiario y no es válida.');
            }
        }

        return Regalia::create([
            'beneficiary_user_id' => $beneficiaryId,
            'source_type'         => $sourceType,
            'source_id'           => $sourceId,
            'commission'          => 0,
        ]);
    }

    /**
     * Actualiza el porcentaje de comisión de una Regalía.
     * Aplica clamp 0–100 antes de guardar.
     */
    public function updateCommission(Regalia $regalia, ?float $commission): Regalia
    {
        if ($commission === null) {
            $regalia->commission = 0;
        } else {
            $regalia->commission = (float) $commission;
        }

        // Clamp por seguridad
        if ($regalia->commission < 0) {
            $regalia->commission = 0;
        } elseif ($regalia->commission > 100) {
            $regalia->commission = 100;
        }

        $regalia->save();

        return $regalia;
    }

    /**
     * Elimina una Regalía y devuelve el payload básico
     * que se necesita para responder al front.
     */
    public function deleteRegalia(Regalia $regalia): array
    {
        $payload = [
            'id'                  => $regalia->id,
            'beneficiary_user_id' => $regalia->beneficiary_user_id,
            'source_type'         => $regalia->source_type,
            'source_id'           => $regalia->source_id,
        ];

        $regalia->delete();

        return $payload;
    }

    /**
     * Verifica si al crear una relación de regalía usuario-usuario
     * beneficiaryId -> sourceId se generaría un ciclo en el grafo.
     *
     * Lógica:
     *  - El grafo está definido por las aristas (beneficiary_user_id) -> (source_id)
     *    de todas las Regalia con source_type = 'user'.
     *  - Agregar beneficiaryId -> sourceId genera un ciclo si YA existe
     *    un camino desde sourceId hasta beneficiaryId.
     */
    protected function wouldCreateUserCycle(int $beneficiaryId, int $sourceId): bool
    {
        // Ciclo trivial: usuario que se apunta a sí mismo.
        if ($beneficiaryId === $sourceId) {
            return true;
        }

        // Cargamos todas las relaciones usuario-usuario existentes.
        $rows = Regalia::query()
            ->where('source_type', 'user')
            ->get(['beneficiary_user_id', 'source_id']);

        // Construimos lista de adyacencia: from => [to1, to2, ...]
        $adjacency = [];

        foreach ($rows as $row) {
            $from = (int) $row->beneficiary_user_id;
            $to   = (int) $row->source_id;

            if (!isset($adjacency[$from])) {
                $adjacency[$from] = [];
            }

            // Usamos las keys como set para evitar duplicados
            $adjacency[$from][$to] = true;
        }

        // Búsqueda en profundidad/anchura desde sourceId:
        // si alcanzamos beneficiaryId, el nuevo enlace cerraría un ciclo.
        $stack   = [$sourceId];
        $visited = [$sourceId => true];

        while (!empty($stack)) {
            $current = array_pop($stack);

            if ($current === $beneficiaryId) {
                return true;
            }

            if (!isset($adjacency[$current])) {
                continue;
            }

            foreach ($adjacency[$current] as $neighbor => $_) {
                if (!isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $stack[] = $neighbor;
                }
            }
        }

        return false;
    }

    /**
     * Verifica si al crear una regalía de unidad
     * beneficiaryId -> unitId (source_type = 'unit')
     * se generaría una redundancia cíclica en la jerarquía de unidades
     * para ese beneficiario.
     *
     * Reglas:
     *  - Tomar todas las unidades que el beneficiario ya tiene asignadas
     *    como origen de regalía (source_type = 'unit').
     *  - Para cada unidad existente Ue, subir por ancestorChain() y verificar
     *    que la unidad candidata (unitId) NO esté en esa cadena.
     *    (evita que la nueva unidad sea ancestro/igual de una ya asignada).
     *  - Tomar la unidad candidata y subir por ancestorChain() y verificar
     *    que NINGUNA de las unidades ya asignadas esté en esa cadena.
     *    (evita que una ya asignada sea ancestro/igual de la nueva).
     */
    protected function wouldCreateUnitRedundancyCycle(int $beneficiaryId, int $unitId): bool
    {
        // Todas las unidades ya asignadas (origen unit) para este beneficiario
        $existingUnitIds = Regalia::query()
            ->where('beneficiary_user_id', $beneficiaryId)
            ->where('source_type', 'unit')
            ->pluck('source_id')
            ->filter()
            ->map(static function ($v) {
                return (int) $v;
            })
            ->unique()
            ->values()
            ->all();

        if (empty($existingUnitIds)) {
            // No hay unidades previas, no puede haber redundancia
            return false;
        }

        // Si por alguna razón la nueva unidad ya está en la lista,
        // es redundancia (aunque en práctica lo habría detenido el check de duplicado).
        if (in_array($unitId, $existingUnitIds, true)) {
            return true;
        }

        // Cargamos todas las unidades relevantes (existentes + candidata)
        $unitIdsToLoad = array_unique(array_merge($existingUnitIds, [$unitId]));

        /** @var \Illuminate\Support\Collection<int,\App\Models\BusinessUnit> $unitsMap */
        $unitsMap = BusinessUnit::query()
            ->whereIn('id', $unitIdsToLoad)
            ->get()
            ->keyBy('id');

        /** @var BusinessUnit|null $candidate */
        $candidate = $unitsMap->get($unitId);

        if (!$candidate) {
            // En teoría no debería ocurrir porque la capa HTTP hace findOrFail($unitId),
            // pero si pasa, consideramos que no podemos validar bien.
            return false;
        }

        // Cadena de ancestros (root -> ... -> candidate) de la unidad candidata
        $candidateChainIds = $candidate->ancestorChain()
            ->pluck('id')
            ->map(static function ($v) {
                return (int) $v;
            })
            ->all();

        // Regla 1:
        // Para cada unidad ya asignada, verificamos que la candidata no esté
        // en su cadena de ancestros (root -> ... -> Ue).
        foreach ($existingUnitIds as $existingId) {
            /** @var BusinessUnit|null $existing */
            $existing = $unitsMap->get($existingId);
            if (!$existing) {
                continue;
            }

            $chainIds = $existing->ancestorChain()
                ->pluck('id')
                ->map(static function ($v) {
                    return (int) $v;
                })
                ->all();

            if (in_array($unitId, $chainIds, true)) {
                // La unidad candidata aparece en la cadena de ancestros de una ya asignada
                // => la nueva es ancestro/igual de esa unidad => redundancia
                return true;
            }
        }

        // Regla 2:
        // Revisamos que ninguna de las unidades ya asignadas esté en la cadena
        // de ancestros de la unidad candidata.
        foreach ($existingUnitIds as $existingId) {
            if (in_array($existingId, $candidateChainIds, true)) {
                // Una unidad ya asignada aparece en la cadena de la nueva
                // => esa unidad es ancestro/igual de la candidata => redundancia
                return true;
            }
        }

        return false;
    }
}
