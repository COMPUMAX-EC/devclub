<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\BusinessUnitMembership;
use App\Models\User;
use App\Services\BusinessUnits\BusinessUnitPermissionResolver;
use Illuminate\Http\Request;

class UserUnitsDebugController extends Controller
{
	private BusinessUnitPermissionResolver $resolver;

	public function __construct(BusinessUnitPermissionResolver $resolver)
	{
		$this->resolver = $resolver;
	}

	/**
	 * Página HTML que carga el componente Vue.
	 */
	public function page(Request $request)
	{
		// Pantalla de debug: sin validación de permisos del actor.
		return view('admin.debug.user-units');
	}

	/**
	 * API: Lista paginada de usuarios con sus membresías/unidades.
	 *
	 * GET admin.debug.user-units.index
	 */
	public function index(Request $request)
	{
		// Sin validación de permisos: herramienta de debug para programador.

		$data = $request->validate([
			'q'        => ['nullable', 'string', 'max:255'],
			'page'     => ['nullable', 'integer', 'min:1'],
			'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
		]);

		$q       = trim((string) ($data['q'] ?? ''));
		$perPage = (int) ($data['per_page'] ?? 25);

		// Solo usuarios que tengan AL MENOS una membresía
		$query = User::query()
			->whereExists(function ($q2) {
				$q2->selectRaw('1')
					->from('memberships_business_unit')
					->whereColumn('memberships_business_unit.user_id', 'users.id');
			});

		if ($q !== '') {
			$query->where(function ($qq) use ($q) {
				$qq->where('email', 'like', '%' . $q . '%')
					->orWhere('first_name', 'like', '%' . $q . '%')
					->orWhere('last_name', 'like', '%' . $q . '%')
					->orWhere('display_name', 'like', '%' . $q . '%');
			});
		}

		$pag = $query
			->orderBy('email')
			->paginate($perPage);

		$items   = $pag->items();
		$userIds = collect($items)->pluck('id')->all();

		// Cargamos membresías + unidad + rol para todos los usuarios de la página
		$memberships = BusinessUnitMembership::query()
			->whereIn('user_id', $userIds)
			->with(['unit', 'role'])
			->orderBy('id')
			->get();

		$membershipsByUser = [];
		foreach ($memberships as $m) {
			$membershipsByUser[$m->user_id][] = $m;
		}

		$out = [];
		foreach ($items as $u) {
			if (!array_key_exists($u->id, $membershipsByUser)) {
				throw new \RuntimeException('Usuario sin membresías cargadas en debug: ' . $u->id);
			}

			$userMemberships = $membershipsByUser[$u->id];
			$mOut            = [];

			foreach ($userMemberships as $m) {
				if (!$m->unit) {
					throw new \RuntimeException('Membresía sin unidad asociada: ' . $m->id);
				}

				$role = $m->role;

				$mOut[] = [
					'id'     => $m->id,
					'status' => $m->status,
					'role'   => $role ? [
						'id'   => $role->id,
						'name' => $role->name,
					] : null,
					'unit'   => [
						'id'        => $m->unit->id,
						'name'      => $m->unit->name,
						'type'      => $m->unit->type,
						'status'    => $m->unit->status,
						'parent_id' => $m->unit->parent_id,
					],
				];
			}

			if (empty($mOut)) {
				throw new \RuntimeException('Usuario sin unidades válidas en debug: ' . $u->id);
			}

			$out[] = [
				'id'           => $u->id,
				'email'        => $u->email,
				'display_name' => $u->displayName(),
				'first_name'   => $u->first_name,
				'last_name'    => $u->last_name,
				'status'       => $u->status,
				'memberships'  => $mOut,
			];
		}

		// Listado completo de unidades para poder navegar el árbol (padre -> hijos)
		$allUnits = BusinessUnit::query()
			->select(['id', 'name', 'type', 'status', 'parent_id'])
			->orderBy('name')
			->get()
			->map(function (BusinessUnit $unit) {
				return [
					'id'        => $unit->id,
					'name'      => $unit->name,
					'type'      => $unit->type,
					'status'    => $unit->status,
					'parent_id' => $unit->parent_id,
				];
			})
			->values();

		return response()->json([
			'data'  => $out,
			'meta'  => [
				'pagination' => [
					'current_page' => $pag->currentPage(),
					'last_page'    => $pag->lastPage(),
					'per_page'     => $pag->perPage(),
					'total'        => $pag->total(),
				],
			],
			// aquí va el árbol base de unidades (sin validar permisos)
			'units' => $allUnits,
		]);
	}

	/**
	 * API: Permisos/abilities efectivos para un usuario en una unidad.
	 *
	 * GET admin.debug.user-units.abilities
	 * Params: user_id, unit_id
	 */
	public function abilities(Request $request)
	{
		// Sin validación de permisos: herramienta de debug.

		$data = $request->validate([
			'user_id' => ['required', 'integer', 'exists:users,id'],
			'unit_id' => ['required', 'integer', 'exists:business_units,id'],
		]);

		$user = User::query()->findOrFail($data['user_id']);
		$unit = BusinessUnit::query()->findOrFail($data['unit_id']);

		// Calculamos abilities efectivos para ESE usuario en ESA unidad.
		$abilitiesObj = $this->resolver->forUnit($user, $unit);

		return response()->json([
			'data' => [
				'permissions'          => $abilitiesObj->getPermissions(),
				'abilities'            => $abilitiesObj->toArray(),
				'ability_requirements' => $abilitiesObj->abilityRequirements(),
			],
		]);
	}
}
