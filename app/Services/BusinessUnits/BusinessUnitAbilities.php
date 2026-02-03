<?php

namespace App\Services\BusinessUnits;

use App\Models\BusinessUnit;
use App\Models\User;

class BusinessUnitAbilities
{

	/**
	 * Niveles efectivos de permisos para la unidad (0..3).
	 *
	 * @var array<string,int>
	 */
	private array $permissionLevels;

	/**
	 * Niveles globales 0..3.
	 *
	 * @var array<string,int>
	 */
	private array $globalPermissionLevels;
	private ?User $user;
	private ?BusinessUnit $unit;

	/**
	 * Cache de habilidades ya calculadas.
	 *
	 * @var array<string,bool>
	 */
	private array $abilitiesCache = [];

	/**
	 * Mapa: habilidad => [nombre_permiso, nivel_mínimo]
	 *
	 * nivel_mínimo usa las constantes LEVEL_* de BusinessUnitPermissionResolver.
	 *
	 * @var array<string,array{0:string,1:int}>
	 */
	private const ABILITY_PERMISSION_MAP = [
		// Acceso global a la estructura (listado raíz, etc.)
		'can_structure_view'		   => ['unit.structure.view', BusinessUnitPermissionResolver::LEVEL_LOCAL],
		// Datos básicos
		'can_basic_view'			   => ['unit.basic.view', BusinessUnitPermissionResolver::LEVEL_LOCAL],
		'can_basic_edit'			   => ['unit.basic.edit', BusinessUnitPermissionResolver::LEVEL_LOCAL],
		// Branding
		'can_branding_view'			   => ['unit.branding.view', BusinessUnitPermissionResolver::LEVEL_LOCAL],
		'can_branding_manage'		   => ['unit.branding.manage', BusinessUnitPermissionResolver::LEVEL_LOCAL],
		// Miembros
		'can_members_view'			   => ['unit.members.view', BusinessUnitPermissionResolver::LEVEL_LOCAL],
		'can_members_invite'		   => ['unit.members.invite', BusinessUnitPermissionResolver::LEVEL_LOCAL],
		'can_members_manage_roles'	   => ['unit.members.manage_roles', BusinessUnitPermissionResolver::LEVEL_LOCAL], // no local-only
		'can_members_manage_roles_any' => ['unit.members.manage_roles', BusinessUnitPermissionResolver::LEVEL_INHERITED], // no local-only
		'can_members_remove'		   => ['unit.members.remove', BusinessUnitPermissionResolver::LEVEL_INHERITED], // no local-only
		// Hijos (estructura)
		'can_manage_children'		   => ['unit.manage_children', BusinessUnitPermissionResolver::LEVEL_LOCAL],
		// Estado de la unidad
		'can_toggle_status'			   => ['unit.structure.manage', BusinessUnitPermissionResolver::LEVEL_INHERITED], // no local-only
		// Operaciones globales sobre estructura
		'can_move'					   => ['unit.structure.manage', BusinessUnitPermissionResolver::LEVEL_GLOBAL],
		'can_change_type'			   => ['unit.structure.manage', BusinessUnitPermissionResolver::LEVEL_GLOBAL],
		'can_create'				   => ['unit.structure.manage', BusinessUnitPermissionResolver::LEVEL_GLOBAL],
		// Operaciones globales sobre usuarios
		'can_pick_active_users'		   => ['unit.members.invite', BusinessUnitPermissionResolver::LEVEL_GLOBAL],
		'can_products_sell'			   => ['unit.products.sell', BusinessUnitPermissionResolver::LEVEL_GLOBAL],
		'can_access'				   => ['unit.structure.view', BusinessUnitPermissionResolver::LEVEL_LOCAL],
		
		'can_edit_gsa_commission'	   => ['unit.gsa.commission', BusinessUnitPermissionResolver::LEVEL_GLOBAL],
	];

	public function __construct(
			array $permissionLevels,
			array $globalPermissionLevels,
			?User $user = null,
			?BusinessUnit $unit = null
	)
	{
		$this->permissionLevels		  = $permissionLevels;
		$this->globalPermissionLevels = $globalPermissionLevels;
		$this->user					  = $user;
		$this->unit					  = $unit;
	}

	/**
	 * Nivel 0..3 de un permiso concreto.
	 */
	public function permissionLevel(string $permission): int
	{
		return $this->permissionLevels[$permission] ?? BusinessUnitPermissionResolver::LEVEL_NONE;
	}

	/**
	 * true si el permiso alcanza al menos el nivel indicado.
	 */
	private function hasPermission(string $permission, int $minLevel): bool
	{
		return $this->permissionLevel($permission) >= $minLevel;
	}

	/**
	 * true si el permiso existe pero solo es local (nivel 1).
	 */
	public function isOnlyLocal(string $permission): bool
	{
		return $this->permissionLevel($permission) === BusinessUnitPermissionResolver::LEVEL_LOCAL;
	}

	/**
	 * true si el permiso tiene algún componente no local (heredado o global).
	 */
	public function hasNonLocal(string $permission): bool
	{
		return $this->permissionLevel($permission) >= BusinessUnitPermissionResolver::LEVEL_INHERITED;
	}

	/**
	 * Consulta una habilidad por nombre (por ejemplo: can('can_toggle_status')).
	 */
	public function can(string $ability): bool
	{
		if (array_key_exists($ability, $this->abilitiesCache))
		{
			return $this->abilitiesCache[$ability];
		}

		$value							= $this->computeAbility($ability);
		$this->abilitiesCache[$ability] = $value;

		return $value;
	}

	/**
	 * Devuelve todas las habilidades en forma de array asociativo.
	 *
	 * @return array<string,bool>
	 */
	public function toArray(): array
	{
		$all = [];

		// Habilidades derivadas del mapa permiso+nivel
		foreach (self::ABILITY_PERMISSION_MAP as $ability => $_)
		{
			$all[$ability] = $this->can($ability);
		}

		return $all;
	}

	/**
	 * Lógica interna para una habilidad concreta.
	 */
	private function computeAbility(string $ability): bool
	{
		// Habilidades estándar basadas en un permiso + nivel mínimo
		if (isset(self::ABILITY_PERMISSION_MAP[$ability]))
		{
			[$perm, $minLevel] = self::ABILITY_PERMISSION_MAP[$ability];

			return $this->hasPermission($perm, $minLevel);
		}

		// Habilidad no reconocida => false
		return false;
	}

	/**
	 * Indica si el usuario tiene alguna habilidad para gestionar roles
	 * en esta unidad (local o "any").
	 *
	 * Usa las habilidades:
	 * - can_members_manage_roles
	 * - can_members_manage_roles_any
	 */
	public function canManageRoles(): bool
	{
		return $this->can('can_members_manage_roles') || $this->can('can_members_manage_roles_any');
	}

	/**
	 * Devuelve el "nivel mínimo" (0..N) de rol que puede gestionar
	 * el usuario en esta unidad, según las reglas:
	 *
	 * - Si NO tiene ninguna habilidad de gestión de roles → null.
	 * - Si tiene can_members_manage_roles_any → sin límite por nivel
	 *   (devolvemos 0, que es el rol más importante).
	 * - Si solo tiene can_members_manage_roles:
	 *     - Se limita por su propio role.level en la unidad
	 *       (myRoleLevel, 0 = más importante).
	 *     - Si myRoleLevel es null (no tiene membresía/rol), por
	 *       seguridad devolvemos null (no puede gestionar).
	 *
	 * @param  int|null  $myRoleLevel  Nivel del rol del usuario en la unidad
	 *                                 (0 = más importante). Null si no tiene rol.
	 * @return int|null  0..N si puede gestionar, null si no puede gestionar ninguno.
	 */
	public function manageableRoleMinLevel(?int $myRoleLevel): ?int
	{
		// Sin habilidad de gestión de roles, no puede gestionar nada.
		if (!$this->canManageRoles())
		{
			return null;
		}

		// Modo "any": puede gestionar cualquier rol de unidad,
		// independientemente de su propio nivel.
		if ($this->can('can_members_manage_roles_any'))
		{
			// 0 = rol más importante → sirve como "sin límite".
			return 0;
		}

		// Si llegamos aquí, solo tiene can_members_manage_roles (sin *_any*)
		// y se limita por su propio rol dentro de la unidad.
		if (!$this->can('can_members_manage_roles'))
		{
			return null;
		}

		// Si no conocemos su nivel de rol en la unidad, por seguridad
		// no lo dejamos gestionar ninguno.
		if ($myRoleLevel === null)
		{
			return null;
		}

		// Escala invertida: 0 es más importante, números más grandes
		// son menos importantes. Su "límite" es su propio nivel.
		return $myRoleLevel;
	}

	/**
	 * true si el usuario puede gestionar un rol cuyo level es $roleLevel,
	 * siguiendo las reglas de:
	 * - can_members_manage_roles_any
	 * - can_members_manage_roles (limitado por su propio rol)
	 *
	 * @param  int       $roleLevel   Nivel del rol objetivo (0 = más importante).
	 * @param  int|null  $myRoleLevel Nivel del rol del usuario en la unidad
	 *                                (0 = más importante). Null si no tiene rol.
	 */
	public function canManageRoleLevel(int $roleLevel, ?int $myRoleLevel): bool
	{
		$minLevel = $this->manageableRoleMinLevel($myRoleLevel);

		// Si no hay ningún nivel gestionable, no puede gestionar este rol.
		if ($minLevel === null)
		{
			return false;
		}

		// Escala invertida: solo puede gestionar roles cuyo level sea
		// igual o "menos importante" que el mínimo gestionable.
		//
		// Ejemplo:
		//   myRoleLevel = 2  → manageableRoleMinLevel = 2
		//   Puede gestionar roles con level >= 2  (2, 3, 4, ...)
		return $roleLevel >= $minLevel;
	}

	public function abilityRequirements(): array
	{
		$out = [];

		foreach (self::ABILITY_PERMISSION_MAP as $ability => [$perm, $minLevel])
		{
			$out[$ability] = [
				'permission' => $perm,
				'min_level'	 => $minLevel,
			];
		}

		return $out;
	}
	
	public function getPermissions()
	{
		return $this->permissionLevels;
	}
}
