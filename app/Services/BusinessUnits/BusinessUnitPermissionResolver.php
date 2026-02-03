<?php

namespace App\Services\BusinessUnits;

use App\Models\BusinessUnit;
use App\Models\User;
use App\Support\Realm;

class BusinessUnitPermissionResolver
{
    public const LEVEL_NONE      = 0;
    public const LEVEL_LOCAL     = 1;
    public const LEVEL_INHERITED = 2;
    public const LEVEL_GLOBAL    = 3;

    /**
     * Lista maestra de permisos de unidad que nos interesan.
     *
     * @var string[]
     */
    private const PERMISSION_KEYS = [
        'unit.structure.view',
        'unit.structure.manage',

        'unit.basic.view',
        'unit.basic.edit',

        'unit.branding.view',
        'unit.branding.manage',

        'unit.members.view',
        'unit.members.invite',
        'unit.members.manage_roles',
        'unit.members.remove',

        'unit.manage_children',
		
		'unit.gsa.commission',
		'unit.products.sell'
    ];

    /**
     * Permisos globales (sin unidad), nivel 0..3.
     *
     * @var array<string,int>
     */
    private array $globalPermissions = [];

    /**
     * Cache de permisos por unidad (id => [perm => nivel]).
     *
     * @var array<int,array<string,int>>
     */
    private array $unitPermissionsCache = [];
	

    public function loadUser(User $user)
    {
		if(!isset($this->globalPermissions[$user->id]))
		{
			// Permisos globales pre-calculados una sola vez por request
			$this->globalPermissions[$user->id] = $this->buildGlobalPermissions($user);
		}
		
		return $user;
    }

    /**
     * Construye el array de permisos globales:
     * - Se inicializa todo en LEVEL_NONE.
     * - Por cada permiso de la lista maestra, se marca LEVEL_GLOBAL si el usuario lo tiene.
     *
     * @return array<string,int>
     */
    private function buildGlobalPermissions($user): array
    {
		$permissions = $this->emptyPermissions();

        foreach (self::PERMISSION_KEYS as $name)
		{
            if ($user->can($name))
			{
                $permissions[$name] = self::LEVEL_GLOBAL;
            }
        }
        return $permissions;
    }

    /**
     * Devuelve un array de permisos inicializado en LEVEL_NONE para todos los keys.
     *
     * @return array<string,int>
     */
    private function emptyPermissions(): array
    {
        return array_fill_keys(self::PERMISSION_KEYS, self::LEVEL_NONE);
    }

    /**
     * Devuelve los niveles de permiso efectivos (0..3) para una unidad concreta.
     *
     * Si $unit es null, devuelve solo los permisos globales.
     *
     * @return array<string,int>
     */
    private function permissionLevelsForUnit($user, BusinessUnit $unit = null): array
    {
		// Sin unidad => solo globales
        if ($unit === null) {
            return $this->globalPermissions[$user->id];
        }
        $key = (int) $unit->id;
		
        if (isset($this->unitPermissionsCache[$user->id][$key])) {
            return $this->unitPermissionsCache[$user->id][$key];
        }

        // Cadena de ancestros (root -> ... -> unidad objetivo)
        $chain = $unit->loadMissing('parent')->ancestorChain();
        if ($chain->isEmpty()) {
            // Fallback defensivo: al menos incluir la unidad actual
            $chain = collect([$unit]);
        }

        $unitIds = $chain->pluck('id')->all();

        // Membresías del usuario en las unidades de la cadena
        $memberships = $user->businessUnitMemberships()
            ->with(['role.permissions'])
            ->whereIn('business_unit_id', $unitIds)
            ->get()
            ->keyBy('business_unit_id');

        /** @var array<int,array<int,string>> $localPermsByUnitId */
        $localPermsByUnitId = [];

        foreach ($chain as $u) {
            $localNames = [];
            $membership = $memberships->get($u->id);

            if ($membership && $membership->role) {
                foreach ($membership->role->permissions as $perm) {
                    $name = (string) $perm->name;

                    // Solo nos interesan los permisos de la lista maestra
                    if (in_array($name, self::PERMISSION_KEYS, true)) {
                        $localNames[$name] = true;
                    }
                }
            }

            $localPermsByUnitId[$u->id] = array_keys($localNames);
        }

        /** @var array<int,array<string,int>> $permLevelsByUnitId */
        $permLevelsByUnitId = [];

        foreach ($chain as $index => $u) {
            // Base: permisos globales
            $levels = $this->globalPermissions[$user->id];

            // 1) Permisos locales en esta unidad
            foreach ($localPermsByUnitId[$u->id] as $permName) {
                $current = $levels[$permName] ?? self::LEVEL_NONE;
                if ($current < self::LEVEL_LOCAL) {
                    $levels[$permName] = self::LEVEL_LOCAL;
                }
            }

            // 2) Permisos heredados desde ancestros con unit.manage_children efectivo
            if ($index > 0) {
                for ($j = 0; $j < $index; $j++) {
                    $anc   = $chain[$j];
                    $ancId = (int) $anc->id;

                    $ancLevels = $permLevelsByUnitId[$ancId] ?? $this->globalPermissions[$user->id];
                    $manageChildrenLevel = $ancLevels['unit.manage_children'] ?? self::LEVEL_NONE;

                    if ($manageChildrenLevel > self::LEVEL_NONE) {
                        foreach ($localPermsByUnitId[$ancId] as $permName) {
                            $current = $levels[$permName] ?? self::LEVEL_NONE;
                            if ($current < self::LEVEL_INHERITED) {
                                $levels[$permName] = self::LEVEL_INHERITED;
                            }
                        }
                    }
                }
            }

            $permLevelsByUnitId[(int) $u->id] = $levels;
        }

        $result = $permLevelsByUnitId[$key] ?? $this->globalPermissions[$user->id];

        $this->unitPermissionsCache[$user->id][$key] = $result;

        return $result;
    }

    /**
     * Construye el objeto de habilidades para una unidad concreta o solo globales si $unit = null.
     */
    public function forUnit(User $user, BusinessUnit $unit = null): BusinessUnitAbilities
    {
		$user = $this->loadUser($user);
        $levels = $this->permissionLevelsForUnit($user, $unit);

        return new BusinessUnitAbilities(
            $levels,
            $this->globalPermissions[$user->id],
            $user,
            $unit
        );
    }

}
