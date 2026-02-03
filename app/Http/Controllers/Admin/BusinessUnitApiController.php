<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\BusinessUnitMembership;
use App\Models\Regalia;
use App\Models\Role;
use App\Models\User;
use App\Services\BusinessUnits\BusinessUnitPermissionResolver;
use App\Services\UploadedFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusinessUnitApiController extends Controller
{
	public function __construct(
		private readonly BusinessUnitPermissionResolver $resolver,
		private readonly UploadedFileService $uploadedFileService
	) {}

	public function list(Request $request)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		// Habilidades globales (sin unidad)
		$globalAbilities = $this->resolver->forUnit($user);

		// Acceso al listado raíz: requiere unit.structure.view a nivel global
		abort_unless($globalAbilities->can('can_structure_view'), 403);

		$data = $request->validate([
			'type'	   => ['required', Rule::in([
				BusinessUnit::TYPE_CONSOLIDATOR,
				BusinessUnit::TYPE_OFFICE,
				BusinessUnit::TYPE_FREELANCE,
				BusinessUnit::TYPE_COUNTER,
			])],
			'status'   => ['nullable', Rule::in(['active', 'inactive', 'all'])],
			'root'	   => ['nullable'], // parseamos manual para aceptar true/false/1/0
			'q'		   => ['nullable', 'string', 'max:255'],
			'page'	   => ['nullable', 'integer', 'min:1'],
			'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
		]);

		$status = $data['status'] ?? 'active';

		$rootParsed = $this->parseBoolean($request->query('root', true));
		if ($rootParsed === null)
		{
			return response()->json([
				'message' => 'The root field must be true or false.',
				'errors'  => ['root' => ['The root field must be true or false.']],
			], 422);
		}

		$perPage = (int) ($data['per_page'] ?? 25);
		$q		 = trim((string) ($data['q'] ?? ''));

		$query = BusinessUnit::query()
			->where('type', $data['type'])
			->with(['parent:id,name,type,status'])
			->withCount([
				'children as children_count',
				'memberships as members_count',
			])
			->when($rootParsed, fn($qq) => $qq->whereNull('parent_id'));

		if ($status !== 'all')
		{
			$query->where('status', $status);
		}

		$isFreelance = ($data['type'] === BusinessUnit::TYPE_FREELANCE);

		if ($isFreelance)
		{
			// Cargamos el usuario owner (freelance tiene 1 membership)
			$query->with([
				'memberships' => function ($mq)
				{
					$mq->with(['user'])
						->orderBy('id');
				}
			]);

			if ($q !== '')
			{
				$query->whereHas('memberships.user', function ($uq) use ($q)
				{
					$uq->where('first_name', 'like', '%' . $q . '%')
						->orWhere('last_name', 'like', '%' . $q . '%')
						->orWhere('email', 'like', '%' . $q . '%');
				});
			}

			// Orden por email del owner (subquery)
			$query->orderBy(
				User::select('email')
					->join('memberships_business_unit', 'users.id', '=', 'memberships_business_unit.user_id')
					->whereColumn('memberships_business_unit.business_unit_id', 'business_units.id')
					->limit(1)
			);
		}
		else
		{
			if ($q !== '')
			{
				$query->where(function ($qq) use ($q)
				{
					if (ctype_digit($q))
					{
						$qq->orWhere('id', (int) $q);
					}
					$qq->orWhere('name', 'like', '%' . $q . '%');
				});
			}

			$query->orderBy('name');
		}

		$pag = $query->paginate($perPage);

		$out = [];
		foreach ($pag->items() as $u)
		{
			$abilities = $this->resolver->forUnit($user, $u);

			$ownerUser = null;
			if ($isFreelance)
			{
				$m = $u->memberships->first();
				if ($m && $m->user)
				{
					$ownerUser = $m->user;
				}
			}

			$row = [
				'id'			 => $u->id,
				'type'			 => $u->type,
				'name'			 => $u->name,
				'status'		 => $u->status,
				'parent_id'		 => $u->parent_id,
				'parent'		 => $u->parent ? [
					'id'	 => $u->parent->id,
					'name'	 => $u->parent->name,
					'type'	 => $u->parent->type,
					'status' => $u->parent->status,
				] : null,
				'children_count' => (int) ($u->children_count ?? 0),
				'members_count'	 => (int) ($u->members_count ?? 0),
				'abilities'		 => $abilities->toArray(),
			];

			if ($isFreelance)
			{
				$row['owner_user'] = $ownerUser ? [
					'id'		   => $ownerUser->id,
					'email'		   => $ownerUser->email,
					'first_name'   => $ownerUser->first_name,
					'last_name'	   => $ownerUser->last_name,
					'display_name' => $ownerUser->displayName(),
					'status'	   => $ownerUser->status,
				] : null;
			}

			$out[] = $row;
		}

		return response()->json([
			'data' => $out,
			'meta' => [
				'pagination'  => [
					'current_page' => $pag->currentPage(),
					'last_page'	   => $pag->lastPage(),
					'per_page'	   => $pag->perPage(),
					'total'		   => $pag->total(),
				],
				'permissions' => $globalAbilities->toArray(),
			],
		]);
	}

	public function show(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);

		// Nivel numérico de unit.members.manage_roles (0..3)
		$manageRolesLevel = $abilities->permissionLevel('unit.members.manage_roles');

		$unit->loadMissing(['parent', 'logoFile'])
			->loadCount(['children', 'memberships']);

		return response()->json([
			'data' => [
				'id' => $unit->id,
				'type' => $unit->type,
				'name' => $unit->name,
				'status' => $unit->status,
				'parent_id' => $unit->parent_id,
				'parent' => $unit->parent ? [
					'id' => $unit->parent->id,
					'name' => $unit->parent->name,
					'type' => $unit->parent->type,
					'status' => $unit->parent->status,
				] : null,
				'children_count' => (int) ($unit->children_count ?? 0),
				'memberships_count' => (int) ($unit->memberships_count ?? 0),

				'branding' => [
					'branding_text_dark' => $unit->branding_text_dark,
					'branding_bg_light' => $unit->branding_bg_light,
					'branding_text_light' => $unit->branding_text_light,
					'branding_bg_dark' => $unit->branding_bg_dark,
					'branding_logo_file_id' => $unit->branding_logo_file_id,
					'branding_logo_url' => $unit->logoFile ? $unit->logoFile->url() : null,
				],
				'branding_effective' => $unit->effectiveBranding(),
				'system_logo_constraints' => $unit->systemLogoConstraints(),

				'abilities' => $abilities->toArray(),
			],
		]);
	}

	public function children(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);

		$data = $request->validate([
			'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
		]);

		$status = $data['status'] ?? 'active';

		$children = $unit->children()
			->withCount([
				'children as children_count',
				'memberships as members_count',
			])
			->when($status !== 'all', fn($q) => $q->where('status', $status))
			->orderBy('name')
			->get();

		$out = [];
		foreach ($children as $c) {
			$abilities = $this->resolver->forUnit($user, $c);

			$out[] = [
				'id' => $c->id,
				'type' => $c->type,
				'name' => $c->name,
				'status' => $c->status,
				'parent_id' => $c->parent_id,
				'children_count' => (int) ($c->children_count ?? 0),
				'members_count' => (int) ($c->members_count ?? 0),
				'abilities' => $abilities->toArray(),
			];
		}

		return response()->json(['data' => $out]);
	}

	public function rolesUnitScope(Request $request)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$data = $request->validate([
			'unit_id' => ['required', 'integer', 'exists:business_units,id'],
		]);

		$unit = BusinessUnit::query()->findOrFail($data['unit_id']);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);

		$roles = $this->rolesManageableForUnit($user, $unit);

		if ($roles->isEmpty())
		{
			return response()->json(['data' => []]);
		}

		$out = $roles->map(function (Role $role)
		{
			return [
				'id'		=> $role->id,
				'name'		=> $role->name,
				'scope'		=> $role->scope,
				'level'		=> $role->level,
				'role_name' => $role->role_name, // accessor + appends
			];
		})->values();

		return response()->json(['data' => $out]);
	}

	public function store(Request $request)
	{
		$user = $request->user('admin');
		abort_unless($user && $user->can('unit.structure.manage'), 403); // crear = global

		$data = $request->validate([
			'type' => ['required', Rule::in([
				BusinessUnit::TYPE_CONSOLIDATOR,
				BusinessUnit::TYPE_OFFICE,
				BusinessUnit::TYPE_COUNTER,
				BusinessUnit::TYPE_FREELANCE,
			])],

			// Para consolidator/office/counter
			'name' => ['nullable', 'string', 'max:255'],
			'parent_id' => ['nullable', 'integer', 'exists:business_units,id'],

			// freelance: modo
			'mode' => ['nullable', Rule::in(['new_user', 'existing_user', 'email_exact'])],
			'existing_user_id' => ['nullable', 'integer', 'exists:users,id'],
			'email' => ['nullable', 'email', 'max:255'],

			// new user (SIN password)
			'user.first_name' => ['nullable', 'string', 'max:255'],
			'user.last_name' => ['nullable', 'string', 'max:255'],
			'user.email' => ['nullable', 'email', 'max:255'],
		]);

		$type = $data['type'];

		$parent = null;
		if (!is_null($data['parent_id'] ?? null)) {
			$parent = BusinessUnit::query()->findOrFail($data['parent_id']);
		}

		$this->assertParentRules($type, $parent);

		DB::beginTransaction();
		try {
			$unit = new BusinessUnit();
			$unit->type = $type;
			$unit->status = BusinessUnit::STATUS_ACTIVE;

			if ($type === BusinessUnit::TYPE_FREELANCE) {
				$unit->parent_id = null;

				// Requisito: para freelances no se llena nombre de unidad (queda en blanco)
				$unit->name = '';

				$mode = $data['mode'] ?? 'existing_user';

				if ($mode === 'new_user') {
					$u = $this->createAdminUserForFreelance($data);
					$unit->save();

					$this->attachFreelanceOwner($unit, $u);
				}
				elseif ($mode === 'existing_user') {
					$existingUserId = $data['existing_user_id'] ?? null;
					if (!$existingUserId) {
						return response()->json(['message' => 'existing_user_id requerido.'], 422);
					}

					$u = User::query()->findOrFail($existingUserId);
					if ($u->status !== 'active') {
						return response()->json(['message' => 'Usuario no activo.'], 422);
					}

					$unit->save();
					$this->attachFreelanceOwner($unit, $u);
				}
				else { // email_exact
					$email = (string) ($data['email'] ?? '');
					if ($email === '') {
						return response()->json(['message' => 'Email requerido.'], 422);
					}

					$u = User::query()->where('email', $email)->first();
					if (!$u) {
						return response()->json(['message' => 'No existe un usuario con ese email.'], 422);
					}
					if ($u->status !== 'active') {
						return response()->json(['message' => 'Usuario no activo.'], 422);
					}

					$unit->save();
					$this->attachFreelanceOwner($unit, $u);
				}
			}
			else {
				$name = trim((string) ($data['name'] ?? ''));
				if ($name === '') {
					return response()->json(['message' => 'name requerido.'], 422);
				}

				$unit->name = $name;
				$unit->parent_id = $parent ? $parent->id : null;
				$unit->save();
			}

			DB::commit();

			return response()->json([
				'message' => 'Unidad creada.',
				'data' => ['id' => $unit->id],
			]);
		}
		catch (\Throwable $e) {
			DB::rollBack();
			return response()->json(['message' => $e->getMessage()], 422);
		}
	}

	public function updateBasic(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);
		abort_unless($abilities->can('can_basic_edit'), 403);

		$data = $request->validate([
			'name' => ['required', 'string', 'max:255'],
		]);

		$unit->name = $data['name'];
		$unit->save();

		return response()->json(['message' => 'Guardado.']);
	}

	public function updateStatus(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);
		abort_unless($abilities->can('can_toggle_status'), 403);

		$data = $request->validate([
			'status' => ['required', Rule::in([BusinessUnit::STATUS_ACTIVE, BusinessUnit::STATUS_INACTIVE])],
		]);

		$unit->status = $data['status'];
		$unit->save();

		return response()->json(['message' => 'Guardado.']);
	}

	public function changeType(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user && $user->can('unit.structure.manage'), 403); // convertir = global

		$data = $request->validate([
			'target_type' => ['required', Rule::in([
				BusinessUnit::TYPE_FREELANCE,
				BusinessUnit::TYPE_OFFICE,
				BusinessUnit::TYPE_COUNTER,
				BusinessUnit::TYPE_CONSOLIDATOR,
			])],
			'detach_parent' => ['nullable'],
		]);

		$from = $unit->type;
		$to = $data['target_type'];

		$detachParent = $this->parseBoolean($request->input('detach_parent', false)) === true;

		// Reglas (las que indicaste)
		// freelance -> office
		// office -> consolidator (solo si office NO tiene padre)
		// office -> office independiente (si tiene padre)  => se vuelve parent_id = null, type se mantiene office
		// counter -> agencia independiente => counter -> office con parent_id = null

		if ($from === BusinessUnit::TYPE_FREELANCE && $to === BusinessUnit::TYPE_OFFICE) {
			$unit->type = BusinessUnit::TYPE_OFFICE;
			$unit->parent_id = null;
		}
		elseif ($from === BusinessUnit::TYPE_OFFICE && $to === BusinessUnit::TYPE_CONSOLIDATOR) {
			if (!is_null($unit->parent_id)) {
				return response()->json(['message' => 'Solo una office sin padre puede convertirse en consolidator.'], 422);
			}
			$unit->type = BusinessUnit::TYPE_CONSOLIDATOR;
			$unit->parent_id = null;
		}
		elseif ($from === BusinessUnit::TYPE_OFFICE && $to === BusinessUnit::TYPE_OFFICE && $detachParent) {
			if (is_null($unit->parent_id)) {
				return response()->json(['message' => 'La office ya es independiente.'], 422);
			}
			$unit->parent_id = null;
		}
		elseif ($from === BusinessUnit::TYPE_COUNTER && $to === BusinessUnit::TYPE_OFFICE) {
			$unit->type = BusinessUnit::TYPE_OFFICE;
			$unit->parent_id = null;
		}
		else {
			return response()->json(['message' => 'Conversión no permitida.'], 422);
		}

		// Revalidamos reglas de padre con el estado final (sin usar relación cacheada)
		$newParent = null;
		if (!is_null($unit->parent_id)) {
			$newParent = BusinessUnit::query()->find($unit->parent_id);
		}

		$this->assertParentRules($unit->type, $newParent);

		$unit->save();

		return response()->json(['message' => 'Tipo actualizado.']);
	}

	public function move(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user && $user->can('unit.structure.manage'), 403); // mover = global

		$data = $request->validate([
			'parent_id' => ['nullable', 'integer', 'exists:business_units,id'],
		]);

		$newParent = null;
		if (!is_null($data['parent_id'])) {
			$newParent = BusinessUnit::query()->findOrFail($data['parent_id']);
		}

		$this->assertParentRules($unit->type, $newParent);

		if ($newParent) {
			if ((int) $newParent->id === (int) $unit->id) {
				return response()->json(['message' => 'Parent inválido.'], 422);
			}

			$node = $newParent->loadMissing('parent');
			while ($node) {
				if ((int) $node->id === (int) $unit->id) {
					return response()->json(['message' => 'Parent inválido (ciclo).'], 422);
				}
				$node = $node->parent;
			}
		}

		$unit->parent_id = $newParent ? $newParent->id : null;
		$unit->save();

		return response()->json(['message' => 'Movida.']);
	}

	public function updateBranding(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);

		if ($unit->isFreelance()) {
			return response()->json(['message' => 'Freelance no permite editar branding.'], 422);
		}

		abort_unless($abilities->can('can_branding_manage'), 403);

		$data = $request->validate([
			'branding_text_dark' => ['nullable', 'string', 'max:12'],
			'branding_bg_light' => ['nullable', 'string', 'max:12'],
			'branding_text_light' => ['nullable', 'string', 'max:12'],
			'branding_bg_dark' => ['nullable', 'string', 'max:12'],
			'logo' => ['nullable', 'file'],
			'remove_logo' => ['nullable'],
		]);

		foreach (['branding_text_dark', 'branding_bg_light', 'branding_text_light', 'branding_bg_dark'] as $f) {
			if (array_key_exists($f, $data)) {
				$v = $data[$f];
				$unit->{$f} = (is_null($v) || trim((string) $v) === '') ? null : (string) $v;
			}
		}

		$removeLogo = $this->parseBoolean($request->input('remove_logo', false)) === true;
		if ($removeLogo) {
			$unit->branding_logo_file_id = null;
		}

		if ($request->hasFile('logo')) {
			$upload = $request->file('logo');

			$meta = [
				'field' => 'branding_logo_file_id',
				'model' => BusinessUnit::class,
				'model_id' => $unit->id,
			];

			$file = $this->uploadedFileService->store(
				$upload,
				basePath: 'business_units/branding',
				meta: $meta,
				uploadedBy: $user->id
			);

			$unit->branding_logo_file_id = $file->id;
		}

		$unit->save();

		return response()->json(['message' => 'Guardado.']);
	}

	public function members(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);
		abort_unless($abilities->can('can_members_view'), 403);

		$memberships = $unit->memberships()
			->with(['user', 'role'])
			->orderBy('id')
			->get();

		$out = [];
		$currentMembershipRow = null;

		foreach ($memberships as $m)
		{
			$row = [
				'id'     => $m->id,
				'status' => $m->status, // active / inactive
				'user'   => [
					'id'		   => $m->user->id,
					'email'		   => $m->user->email,
					'display_name' => $m->user->displayName(),
					'status'	   => $m->user->status,
				],
				'role'   => $m->role ? [
					'id'		=> $m->role->id,
					'name'		=> $m->role->name,
					'level'		=> $m->role->level,
					'role_name' => $m->role->roleName(), // nombre "bonito"
				] : null,
			];

			$out[] = $row;

			if ((int) $m->user_id === (int) $user->id) {
				$currentMembershipRow = $row;
			}
		}

		return response()->json([
			'data' => $out,
			'meta' => [
				'can_pick_active_users'   => $abilities->can('can_pick_active_users'),
				'current_membership' => $currentMembershipRow,
			],
		]);
	}

	public function usersSearchActive(Request $request)
	{
		$user = $request->user('admin');
		abort_unless($user && $user->can('unit.members.invite'), 403); // global => lista paginada

		$data = $request->validate([
			'q' => ['nullable', 'string', 'max:255'],
			'page' => ['nullable', 'integer', 'min:1'],
			'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
		]);

		$q = trim((string) ($data['q'] ?? ''));
		$perPage = (int) ($data['per_page'] ?? 20);

		$query = User::query()
			->where('realm', 'admin')
			->where('status', 'active');

		if ($q !== '') {
			$query->where(function ($qq) use ($q) {
				$qq->where('email', 'like', '%' . $q . '%')
					->orWhere('first_name', 'like', '%' . $q . '%')
					->orWhere('last_name', 'like', '%' . $q . '%')
					->orWhere('display_name', 'like', '%' . $q . '%');
			});
		}

		$pag = $query->orderBy('email')->paginate($perPage);

		$items = [];
		foreach ($pag->items() as $u) {
			$items[] = [
				'id' => $u->id,
				'email' => $u->email,
				'display_name' => $u->displayName(),
				'status' => $u->status,
			];
		}

		return response()->json([
			'data' => $items,
			'meta' => [
				'pagination' => [
					'current_page' => $pag->currentPage(),
					'last_page' => $pag->lastPage(),
					'per_page' => $pag->perPage(),
					'total' => $pag->total(),
				],
			],
		]);
	}

	public function memberLink(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);

		if ($unit->isFreelance()) {
			return response()->json(['message' => 'Freelance no permite vincular miembros.'], 422);
		}

		abort_unless($abilities->can('can_members_invite'), 403);

		$data = $request->validate([
			'mode' => ['required', Rule::in(['email', 'user_id'])],
			'email' => ['nullable', 'email', 'max:255'],
			'user_id' => ['nullable', 'integer', 'exists:users,id'],
			'role_id' => ['required', 'integer', 'exists:roles,id'],
		]);

		$role = Role::query()
			->where('id', $data['role_id'])
			->where('guard_name', 'admin')
			->where('scope', Role::SCOPE_UNIT)
			->first();

		if (!$role) {
			return response()->json(['message' => 'Rol inválido.'], 422);
		}

		$targetUser = null;

		if ($data['mode'] === 'user_id') {
			// Solo global puede ver lista completa
			abort_unless($abilities->can('can_pick_active_users'), 403);

			$targetUser = User::query()->findOrFail($data['user_id']);
			if ($targetUser->status !== 'active') {
				return response()->json(['message' => 'Usuario no activo.'], 422);
			}
		}
		else {
			$email = (string) ($data['email'] ?? '');
			if ($email === '') {
				return response()->json(['message' => 'Email requerido.'], 422);
			}

			$targetUser = User::query()->where('email', $email)->first();
			if (!$targetUser) {
				return response()->json(['message' => 'No existe un usuario con ese email.'], 422);
			}
			if ($targetUser->status !== 'active') {
				return response()->json(['message' => 'Usuario no activo.'], 422);
			}
		}

		$exists = $unit->memberships()->where('user_id', $targetUser->id)->exists();
		if ($exists) {
			return response()->json(['message' => 'El usuario ya es miembro.'], 422);
		}

		$m = new BusinessUnitMembership();
		$m->business_unit_id = $unit->id;
		$m->user_id = $targetUser->id;
		$m->role_id = $role->id;
		$m->status = 'active'; // por defecto activo
		$m->save();

		return response()->json(['message' => 'Vinculado.']);
	}

	public function memberCreateUser(Request $request, BusinessUnit $unit)
	{
		$actor = $request->user('admin');
		abort_unless($actor, 403);

		if ($unit->isFreelance()) {
			return response()->json(['message' => 'Freelance no permite crear usuarios desde esta unidad.'], 422);
		}

		$abilities = $this->resolver->forUnit($actor, $unit);
		abort_unless($abilities->can('can_access'), 403);

		$data = $request->validate([
			'first_name' => ['required', 'string', 'max:255'],
			'last_name' => ['required', 'string', 'max:255'],
			'email' => ['required', 'email', 'max:255'],
			'role_id' => ['required', 'integer'],
		]);

		$roles = $this->rolesManageableForUnit($actor, $unit);
		if ($roles->isEmpty()) {
			return response()->json(['message' => 'No tienes permisos para asignar roles en esta unidad.'], 403);
		}

		$role = $roles->firstWhere('id', (int) $data['role_id']);
		if (!$role) {
			return response()->json(['message' => 'Rol inválido o fuera de alcance.',], 422);
		}

		DB::beginTransaction();
		try {
			// Reutilizamos la lógica de creación de usuario admin (sin password)
			$uData = [
				'user' => [
					'first_name' => $data['first_name'],
					'last_name' => $data['last_name'],
					'email' => $data['email'],
				],
			];

			$user = $this->createAdminUserForFreelance($uData);

			$m = new BusinessUnitMembership();
			$m->business_unit_id = $unit->id;
			$m->user_id = $user->id;
			$m->role_id = $role->id;
			$m->status = 'active'; // por defecto activo
			$m->save();

			DB::commit();

			return response()->json([
				'message' => 'Usuario creado y vinculado.',
				'data' => [
					'membership' => [
						'id' => $m->id,
						'status' => $m->status,
						'user' => [
							'id' => $user->id,
							'email' => $user->email,
							'display_name' => $user->displayName(),
							'status' => $user->status,
						],
						'role' => [
							'id' => $role->id,
							'name' => $role->name,
							'level' => $role->level,
							'role_name' => $role->roleName(),
						],
					],
				],
			]);
		} catch (\Throwable $e) {
			DB::rollBack();
			return response()->json(['message' => $e->getMessage()], 422);
		}
	}

	public function memberUpdateRole(Request $request, BusinessUnit $unit, BusinessUnitMembership $membership)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		if ((int) $membership->business_unit_id !== (int) $unit->id) {
			abort(404);
		}

		if ($unit->isFreelance()) {
			return response()->json(['message' => 'Freelance no permite cambiar roles.'], 422);
		}

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);

		$levelManageRoles = $abilities->permissionLevel('unit.members.manage_roles');
		if ($levelManageRoles <= BusinessUnitPermissionResolver::LEVEL_NONE) {
			abort(403);
		}

		$data = $request->validate([
			'role_id' => ['required', 'integer', 'exists:roles,id'],
		]);

		$newRole = Role::query()
			->where('id', $data['role_id'])
			->where('guard_name', 'admin')
			->where('scope', Role::SCOPE_UNIT)
			->first();

		if (!$newRole) {
			return response()->json(['message' => 'Rol inválido.'], 422);
		}

		// Regla: si el permiso viene SOLO LOCAL, restricciones por level
		if ($levelManageRoles === BusinessUnitPermissionResolver::LEVEL_LOCAL) {
			if ((int) $membership->user_id === (int) $user->id) {
				return response()->json(['message' => 'No puedes cambiar tu rol con permisos solo locales.'], 422);
			}

			$myMembership = $unit->membershipFor($user);
			if (!$myMembership || !$myMembership->role) {
				return response()->json(['message' => 'Membresía inválida.'], 422);
			}

			$myLevel = $myMembership->role->level;
			$targetRole = $membership->role;
			$targetLevel = $targetRole ? $targetRole->level : 999999;
			$newLevel = $newRole->level;

			if ($targetLevel < $myLevel) {
				return response()->json(['message' => 'No puedes administrar un usuario con rol más importante que el tuyo.'], 422);
			}

			if ($newLevel < $myLevel) {
				return response()->json(['message' => 'No puedes asignar un rol más importante que el tuyo.'], 422);
			}
		}

		$membership->role_id = $newRole->id;
		$membership->save();

		return response()->json(['message' => 'Rol actualizado.']);
	}

	public function memberUpdateStatus(Request $request, BusinessUnit $unit, BusinessUnitMembership $membership)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		if ((int) $membership->business_unit_id !== (int) $unit->id) {
			abort(404);
		}

		if ($unit->isFreelance()) {
			return response()->json(['message' => 'Freelance no permite cambiar estado de membresías.'], 422);
		}

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);

		$levelManageRoles = $abilities->permissionLevel('unit.members.manage_roles');
		if ($levelManageRoles <= BusinessUnitPermissionResolver::LEVEL_NONE) {
			abort(403);
		}

		$data = $request->validate([
			'status' => ['required', Rule::in(['active', 'inactive'])],
		]);

		if ($levelManageRoles === BusinessUnitPermissionResolver::LEVEL_LOCAL) {
			// No se permite cambiar el propio estado con permisos solo locales
			if ((int) $membership->user_id === (int) $user->id) {
				return response()->json(['message' => 'No puedes cambiar el estado de tu propia membresía con permisos solo locales.'], 422);
			}

			$myMembership = $unit->membershipFor($user);
			if ($myMembership && $myMembership->role && $membership->role) {
				$myLevel = $myMembership->role->level;
				$targetLevel = $membership->role->level;

				if ($targetLevel < $myLevel) {
					return response()->json(['message' => 'No puedes administrar un usuario con rol más importante que el tuyo.'], 422);
				}
			}
		}

		$membership->status = $data['status'];
		$membership->save();

		$label = $membership->status === 'inactive' ? 'inactiva' : 'activa';

		return response()->json([
			'message' => 'Membresía marcada como ' . $label . '.',
		]);
	}

	public function memberRemove(Request $request, BusinessUnit $unit, BusinessUnitMembership $membership)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		if ((int) $membership->business_unit_id !== (int) $unit->id) {
			abort(404);
		}

		if ($unit->isFreelance()) {
			return response()->json(['message' => 'Freelance no permite desvincular miembros.'], 422);
		}

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_access'), 403);

		$levelRemove = $abilities->permissionLevel('unit.members.remove');
		if ($levelRemove <= BusinessUnitPermissionResolver::LEVEL_NONE) {
			abort(403);
		}

		// removerse a sí mismo requiere heredado o global (no basta local)
		if ((int) $membership->user_id === (int) $user->id) {
			if ($levelRemove <= BusinessUnitPermissionResolver::LEVEL_LOCAL) {
				return response()->json(['message' => 'No puedes removerte con permisos solo locales.'], 422);
			}
		}

		if ($levelRemove === BusinessUnitPermissionResolver::LEVEL_LOCAL) {
			$myMembership = $unit->membershipFor($user);
			if ($myMembership && $myMembership->role && $membership->role) {
				$myLevel = $myMembership->role->level;
				$targetLevel = $membership->role->level;

				if ($targetLevel < $myLevel) {
					return response()->json(['message' => 'No puedes administrar un usuario con rol más importante que el tuyo.'], 422);
				}
			}
		}

		$membership->delete();

		return response()->json(['message' => 'Membresía eliminada.']);
	}

	// -------------------------------------------------
	// Regalías por unidad (reemplazo de comisiones GSA)
	// -------------------------------------------------

	public function gsaCommissions(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_edit_gsa_commission'), 403);

		// Regalías de tipo "unit" con origen en esta unidad
		$rows = Regalia::query()
			->where('source_type', 'unit')
			->where('source_id', $unit->id)
			->get();

		$beneficiaryIds = $rows
			->pluck('beneficiary_user_id')
			->filter()
			->map(static fn($v) => (int) $v)
			->unique()
			->values()
			->all();

		$users = $beneficiaryIds
			? User::query()
				->whereIn('id', $beneficiaryIds)
				->get()
				->keyBy('id')
			: collect();

		$out = [];

		foreach ($rows as $reg)
		{
			/** @var \App\Models\User|null $u */
			$u = $users->get((int) $reg->beneficiary_user_id);

			$out[] = [
				'id'               => $reg->id,
				'business_unit_id' => $unit->id,
				'user_id'          => $u ? $u->id : $reg->beneficiary_user_id,
				'commission'       => (float) $reg->commission,
				'user'             => $u ? [
					'id'           => $u->id,
					'email'        => $u->email,
					'display_name' => $u->displayName(),
					'status'       => $u->status,
				] : null,
			];
		}

		return response()->json(['data' => $out]);
	}

	public function gsaCommissionsAvailable(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_edit_gsa_commission'), 403);

		$data = $request->validate([
			'q'        => ['nullable', 'string', 'max:255'],
			'page'     => ['nullable', 'integer', 'min:1'],
			'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
		]);

		$q       = trim((string) ($data['q'] ?? ''));
		$perPage = (int) ($data['per_page'] ?? 20);

		$query = User::query()
			->where('realm', 'admin')
			->where('status', 'active');

		if ($q !== '')
		{
			$query->where(function ($qq) use ($q)
			{
				$qq->where('email', 'like', '%' . $q . '%')
					->orWhere('first_name', 'like', '%' . $q . '%')
					->orWhere('last_name', 'like', '%' . $q . '%')
					->orWhere('display_name', 'like', '%' . $q . '%');
			});
		}

		$pag = $query->orderBy('email')->paginate($perPage);

		$userIds = [];
		foreach ($pag->items() as $u)
		{
			$userIds[] = $u->id;
		}

		// Regalías existentes para esta unidad y estos usuarios (beneficiarios)
		$existing = Regalia::query()
			->where('source_type', 'unit')
			->where('source_id', $unit->id)
			->whereIn('beneficiary_user_id', $userIds)
			->get()
			->keyBy('beneficiary_user_id');

		$items = [];
		foreach ($pag->items() as $u)
		{
			$reg = $existing->get($u->id);

			$items[] = [
				'id'                 => $u->id,
				'email'              => $u->email,
				'display_name'       => $u->displayName(),
				'status'             => $u->status,
				'is_assigned'        => $reg !== null,
				'commission_user_id' => $reg ? $reg->id : null, // id de Regalia
				'commission'         => $reg ? (float) $reg->commission : null,
			];
		}

		return response()->json([
			'data' => $items,
			'meta' => [
				'pagination' => [
					'current_page' => $pag->currentPage(),
					'last_page'    => $pag->lastPage(),
					'per_page'     => $pag->perPage(),
					'total'        => $pag->total(),
					'from'         => $pag->firstItem(),
					'to'           => $pag->lastItem(),
				],
			],
		]);
	}

	public function gsaCommissionsStore(Request $request, BusinessUnit $unit)
	{
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_edit_gsa_commission'), 403);

		$data = $request->validate([
			'user_id' => ['required', 'integer', 'exists:users,id'],
		]);

		$targetUser = User::query()->findOrFail($data['user_id']);

		if ($targetUser->status !== 'active')
		{
			return response()->json(['message' => 'Usuario no activo.'], 422);
		}

		// Evitar duplicados para esta unidad
		$exists = Regalia::query()
			->where('source_type', 'unit')
			->where('source_id', $unit->id)
			->where('beneficiary_user_id', $targetUser->id)
			->exists();

		if ($exists) {
			return response()->json([
				'message' => 'Ya existe una regalía para este beneficiario y esta unidad.',
			], 422);
		}

		// Validar redundancias cíclicas de unidades para este beneficiario
		if ($this->wouldCreateUnitRedundancyCycleForUnitRegalia($targetUser->id, $unit->id)) {
			return response()->json([
				'message' => 'La unidad seleccionada genera una redundancia en la jerarquía de unidades para este beneficiario y no es válida.',
			], 422);
		}

		$regalia = Regalia::create([
			'beneficiary_user_id' => $targetUser->id,
			'source_type'         => 'unit',
			'source_id'           => $unit->id,
			'commission'          => 0,
		]);

		return response()->json([
			'message' => 'Usuario añadido a las regalias de la unidad.',
			'data'    => [
				'id'               => $regalia->id,
				'business_unit_id' => $unit->id,
				'user_id'          => $targetUser->id,
				'commission'       => (float) $regalia->commission,
				'user'             => [
					'id'           => $targetUser->id,
					'email'        => $targetUser->email,
					'display_name' => $targetUser->displayName(),
					'status'       => $targetUser->status,
				],
			],
		]);
	}

	public function gsaCommissionsUpdate(
		Request $request,
		BusinessUnit $unit,
		Regalia $commissionUser
	) {
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_edit_gsa_commission'), 403);

		// Validar que la regalía corresponda a esta unidad y sea de tipo "unit"
		if (
			$commissionUser->source_type !== 'unit'
			|| (int) $commissionUser->source_id !== (int) $unit->id
		) {
			abort(404);
		}

		$data = $request->validate([
			'commission' => ['nullable', 'numeric', 'min:0', 'max:100'],
		]);

		$numeric = $data['commission'] ?? 0;
		$numeric = max(0, min(100, (float) $numeric));

		$commissionUser->commission = $numeric;
		$commissionUser->save();

		$beneficiary = $commissionUser->beneficiary_user_id
			? User::query()->find($commissionUser->beneficiary_user_id)
			: null;

		return response()->json([
			'message' => 'Comisión actualizada.',
			'data'    => [
				'id'               => $commissionUser->id,
				'business_unit_id' => $unit->id,
				'user_id'          => $commissionUser->beneficiary_user_id,
				'commission'       => (float) $commissionUser->commission,
				'user'             => $beneficiary ? [
					'id'           => $beneficiary->id,
					'email'        => $beneficiary->email,
					'display_name' => $beneficiary->displayName(),
					'status'       => $beneficiary->status,
				] : null,
			],
		]);
	}

	public function gsaCommissionsDestroy(
		Request $request,
		BusinessUnit $unit,
		Regalia $commissionUser
	) {
		$user = $request->user('admin');
		abort_unless($user, 403);

		$abilities = $this->resolver->forUnit($user, $unit);
		abort_unless($abilities->can('can_edit_gsa_commission'), 403);

		// Validar que la regalía corresponda a esta unidad y sea de tipo "unit"
		if (
			$commissionUser->source_type !== 'unit'
			|| (int) $commissionUser->source_id !== (int) $unit->id
		) {
			abort(404);
		}

		$payload = [
			'id'               => $commissionUser->id,
			'business_unit_id' => $unit->id,
			'user_id'          => $commissionUser->beneficiary_user_id,
		];

		$commissionUser->delete();

		return response()->json([
			'message' => 'Usuario removido de las regalias de la unidad.',
			'data'    => $payload,
		]);
	}

	// -------------------------------------------------
	// Helpers
	// -------------------------------------------------

	private function assertParentRules(string $type, ?BusinessUnit $parent): void
	{
		if ($type === BusinessUnit::TYPE_CONSOLIDATOR) {
			if ($parent) throw new \RuntimeException('Un consolidator no puede tener padre.');
			return;
		}

		if ($type === BusinessUnit::TYPE_FREELANCE) {
			if ($parent) throw new \RuntimeException('Un freelance no puede tener padre.');
			return;
		}

		if ($type === BusinessUnit::TYPE_OFFICE) {
			if ($parent && $parent->type !== BusinessUnit::TYPE_CONSOLIDATOR) {
				throw new \RuntimeException('Una office solo puede tener como padre un consolidator (o ser independiente).');
			}
			return;
		}

		if ($type === BusinessUnit::TYPE_COUNTER) {
			if (!$parent) {
				throw new \RuntimeException('Un counter debe tener padre.');
			}

			if (!in_array($parent->type, [BusinessUnit::TYPE_OFFICE, BusinessUnit::TYPE_CONSOLIDATOR], true)) {
				throw new \RuntimeException('Un counter debe tener como padre una office o un consolidator.');
			}
			return;
		}
	}

	private function createAdminUserForFreelance(array $data): User
	{
		$uData = $data['user'] ?? [];

		$first = trim((string) ($uData['first_name'] ?? ''));
		$last = trim((string) ($uData['last_name'] ?? ''));
		$email = trim((string) ($uData['email'] ?? ''));

		if ($first === '' || $last === '' || $email === '') {
			throw new \RuntimeException('Nombre, apellido y correo son requeridos.');
		}

		$exists = User::query()->where('email', $email)->exists();
		if ($exists) {
			throw new \RuntimeException('Ya existe un usuario con ese email.');
		}

		$u = new User();
		$u->realm = 'admin';
		$u->email = $email;
		$u->first_name = $first;
		$u->last_name = $last;
		$u->display_name = trim($first . ' ' . $last);
		$u->status = 'active';

		// Requisito: password NO NULL. Ponemos algo para insertar y luego lo forzamos a "" a nivel DB.
		$u->password = '';

		$u->save();

		return $u;
	}

	private function attachFreelanceOwner(BusinessUnit $unit, User $user): void
	{
		$alreadyHasFreelance = BusinessUnitMembership::query()
			->where('user_id', $user->id)
			->whereHas('unit', fn($q) => $q->where('type', BusinessUnit::TYPE_FREELANCE))
			->exists();

		if ($alreadyHasFreelance) {
			throw new \RuntimeException('El usuario ya tiene una unidad freelance asociada.');
		}

		$ownerRole = Role::query()
			->where('name', 'unit.owner')
			->where('scope', Role::SCOPE_UNIT)
			->where('guard_name', 'admin')
			->first();

		if (!$ownerRole) {
			throw new \RuntimeException('No existe el rol unit.owner (scope=unit).');
		}

		$m = new BusinessUnitMembership();
		$m->business_unit_id = $unit->id;
		$m->user_id = $user->id;
		$m->role_id = $ownerRole->id;
		$m->status = 'active'; // por defecto activo
		$m->save();
	}

	/**
	 * Lista de roles (scope=unit, guard=admin) que el actor puede asignar en la unidad.
	 */
	private function rolesManageableForUnit(User $actor, BusinessUnit $unit)
	{
		$abilities = $this->resolver->forUnit($actor, $unit);

		$levelManageRoles = $abilities->permissionLevel('unit.members.manage_roles');
		if ($levelManageRoles <= BusinessUnitPermissionResolver::LEVEL_NONE) {
			return collect();
		}

		$roles = Role::query()
			->where('scope', Role::SCOPE_UNIT)
			->where('guard_name', 'admin')
			->orderBy('name')
			->get();

		// Si el permiso es solo LOCAL, aplicamos restricciones por level
		if ($levelManageRoles === BusinessUnitPermissionResolver::LEVEL_LOCAL) {
			$myMembership = $unit->membershipFor($actor);

			if (!$myMembership || !$myMembership->role) {
				return collect();
			}

			$myLevel = $myMembership->role->level;

			$roles = $roles->filter(function (Role $role) use ($myLevel) {
				return $role->level >= $myLevel;
			})->values();
		}

		return $roles;
	}

	/**
	 * Verifica si para un beneficiario dado, asignar la unidad $unitId como origen
	 * de regalía (source_type = 'unit') generaría una redundancia en la jerarquía
	 * de unidades (ancestro/descendiente) respecto a las unidades ya asignadas.
	 */
	protected function wouldCreateUnitRedundancyCycleForUnitRegalia(int $beneficiaryId, int $unitId): bool
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
		// es redundancia (además del check de duplicado).
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
			// En teoría no debería ocurrir porque ya validamos la unidad,
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

	private function parseBoolean($value): ?bool
	{
		if (is_bool($value)) return $value;

		if ($value === null) return null;

		if (is_int($value)) {
			if ($value === 1) return true;
			if ($value === 0) return false;
			return null;
		}

		$s = strtolower(trim((string) $value));
		if ($s === '1' || $s === 'true' || $s === 'yes' || $s === 'on') return true;
		if ($s === '0' || $s === 'false' || $s === 'no' || $s === 'off') return false;

		return null;
	}
}
