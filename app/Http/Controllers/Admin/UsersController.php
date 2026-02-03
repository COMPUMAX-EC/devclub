<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\Admin\ResetPasswordAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UsersController extends Controller
{

	public function __construct()
	{
		// Usa tu Policy: AdminUserPolicy (ya mapeada en AppServiceProvider)
		$this->authorizeResource(User::class, 'user');
	}

	/**
	 * GET /admin/users
	 */
	public function index(Request $request)
	{
		$this->authorize('viewAny', User::class);

		$perPage = (int) ($request->input('per_page', 15));
		$status	 = $request->input('status');	 // active|suspended|locked|null
		$role	 = $request->input('role');	   // nombre de rol opcional
		$q		 = $request->input('q');		  // búsqueda simple

		$query = User::query()
				->with(['roles', 'staffProfile'])
				->where('realm', 'admin');

		if ($status)
		{
			$query->where('status', $status);
		}

		if ($q)
		{
			$query->where(function ($qq) use ($q)
			{
				$qq->where('first_name', 'like', "%{$q}%")
						->orWhere('last_name', 'like', "%{$q}%")
						->orWhere('display_name', 'like', "%{$q}%")
						->orWhere('email', 'like', "%{$q}%");
			});
		}

		if ($role)
		{
			$query->whereHas('roles', fn($r) => $r->where('name', $role)->where('guard_name', 'admin'));
		}

		$users = $query->orderBy('id', 'desc')->paginate($perPage)->withQueryString();

		$roles = Role::query()
				->where('guard_name', 'admin')
				->orderBy('name')
				->get(['id', 'name'])
				->pluck('name')
				->all();

		return view('admin.users.index', [
			'title'	  => __('Usuarios'),
			'users'	  => $users,
			'roles'	  => $roles,
			'filters' => [
				'q'		 => $q,
				'status' => $status,
				'role'	 => $role,
				'per'	 => $perPage,
			],
		]);
	}

	/**
	 * GET /admin/users/api/search
	 *
	 * Buscador genérico de usuarios para componentes como UiUserSearchSelector.
	 * Requiere permiso users.viewAny (can('users.viewAny')).
	 */
	public function apiSearch(Request $request)
	{
		$user = Auth::user();

		if (!$user || !$user->can('users.viewAny'))
		{
			abort(403);
		}

		$perPage = (int) $request->input('per_page', 20);
		if ($perPage <= 0)
		{
			$perPage = 20;
		}
		elseif ($perPage > 100)
		{
			$perPage = 100;
		}

		$q      = trim((string) $request->input('q', ''));
		$status = $request->input('status'); // active|suspended|locked|null

		$query = User::query()
				->where('realm', 'admin');

		if ($status)
		{
			$query->where('status', $status);
		}

		if ($q !== '')
		{
			$query->where(function ($qq) use ($q)
			{
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

		$rows = collect($paginator->items())->map(function (User $u)
		{
			$display = $u->display_name;
			if (!$display)
			{
				$display = trim($u->first_name . ' ' . (string) $u->last_name);
			}

			return [
				'id'           => $u->id,
				'display_name' => $display,
				'email'        => $u->email,
				'status'       => $u->status,
			];
		})->values();

		return response()->json([
			'data' => $rows,
			'meta' => [
				'pagination' => [
					'current_page' => $paginator->currentPage(),
					'last_page'    => $paginator->lastPage(),
					'per_page'     => $paginator->perPage(),
					'total'        => $paginator->total(),
					'from'         => $paginator->firstItem() ?? 0,
					'to'           => $paginator->lastItem() ?? 0,
				],
			],
		]);
	}

	/**
	 * GET /admin/users/create
	 */
	public function create()
	{
		$this->authorize('create', User::class);

		$allRoles = Role::query()
				->where('guard_name', 'admin')
				->orderBy('name')
				->pluck('name')
				->all();

		return view('admin.users.create', [
			'title'	   => __('Crear usuario'),
			'allRoles' => $allRoles,
			'defaults' => [
				'status' => 'active',
			],
		]);
	}

	/**
	 * POST /admin/users
	 */
	public function store(Request $request)
	{
		$this->authorize('create', User::class);

		$data = $request->validate([
			'first_name'						=> ['required', 'string', 'max:100'],
			'last_name'							=> ['nullable', 'string', 'max:100'],
			'display_name'						=> ['nullable', 'string', 'max:150'],
			'email'								=> [
				'required', 'email', 'max:150',
				Rule::unique('users')->where(fn($q) => $q->whereNull('deleted_at')->where('realm', 'admin')),
			],
			'status'							=> ['required', 'in:active,suspended,locked'],
			'roles'								=> ['array'],
			'roles.*'							=> ['string', Rule::exists('roles', 'name')->where('guard_name', 'admin')],
			// Staff profile opcional
			'work_phone'						=> ['nullable', 'string', 'max:50'],
			'commission_regular_first_year_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
			'commission_regular_renewal_pct'	=> ['nullable', 'numeric', 'min:0', 'max:100'],
			'commission_capitados_pct'			=> ['nullable', 'numeric', 'min:0', 'max:100'],
		]);

		$roles = collect($data['roles'] ?? []);

		// Regla de comisiones obligatorias si tiene rol vendedor_*
		$this->validateVendorCommissions($roles, $request);

		$user						 = new User();
		$user->realm				 = 'admin';
		$user->first_name			 = $data['first_name'];
		$user->last_name			 = $data['last_name'] ?? null;
		$user->display_name			 = $data['display_name'] ?? null;
		$user->email				 = Str::lower($data['email']);
		$user->status				 = $data['status'];
		// Password temporal aleatorio (se forzará reset por email)
		$user->password				 = \App\Observers\UserObserver::makeTempPassword();
		$user->force_password_change = true;
		$user->save();

		if ($roles->isNotEmpty())
		{
			$user->syncRoles($roles->all());
		}

		// Staff profile (si existe el modelo y relación)
		if (method_exists($user, 'staffProfile'))
		{
			$user->staffProfile()->updateOrCreate(
					['user_id' => $user->id],
					[
						'work_phone'						=> $data['work_phone'] ?? null,
						'commission_regular_first_year_pct' => $data['commission_regular_first_year_pct'] ?? null,
						'commission_regular_renewal_pct'	=> $data['commission_regular_renewal_pct'] ?? null,
						'commission_capitados_pct'			=> $data['commission_capitados_pct'] ?? null,
					]
			);
		}

		session()->flash('status', __('Usuario creado correctamente.'));
		return redirect()->route('admin.users.edit', $user);
	}

	/**
	 * GET /admin/users/{user}
	 */
	public function show(User $user)
	{
		$this->authorize('view', $user);

		$user->load(['roles', 'staffProfile']);

		return view('admin.users.show', [
			'title' => __('Detalle de usuario'),
			'user'	=> $user,
		]);
	}

	public function edit(User $user)
	{
		$this->authorize('update', $user);

		$allRoles = \Spatie\Permission\Models\Role::query()
				->where('guard_name', $user->realm ?? 'admin')
				->orderBy('name')
				->pluck('name')
				->all();

		$assignedRoles = $user->getRoleNames()->toArray();

		return view('admin.users.edit', [
			'user'			=> $user,
			'allRoles'		=> $allRoles,
			'assignedRoles' => $assignedRoles,
		]);
	}

	public function update(Request $request, User $user)
	{
		// Normalizar email (lower/trim)
		$request->merge([
			'email' => Str::lower(trim((string) $request->input('email', ''))),
		]);

		$realm = $user->realm ?? 'admin';

		// Si NO puede editar email/estado, fijamos el valor actual antes de validar
		if (!\Gate::allows('users.email.update'))
		{
			$request->merge(['email' => $user->email]);
		}
		if (!\Gate::allows('users.status.update'))
		{
			$request->merge(['status' => $user->status]);
		}

		// ¿Puede asignar roles?
		$canAssignRoles		= \Gate::allows('users.roles.assign');
		// ¿Puede editar comisiones?
		$canEditCommissions = \Gate::allows('users.commissions.edit');

		// Base de reglas
		$rules = [
			'first_name'   => ['required', 'string', 'max:100'],
			'last_name'	   => ['required', 'string', 'max:100'],
			'display_name' => ['nullable', 'string', 'max:150'],
			'status'	   => ['required', Rule::in(['active', 'suspended', 'locked'])],
			'email'		   => [
				'bail', 'required', 'string', 'email:filter',
						Rule::unique('users', 'email')
						->ignore($user->id)
						->where(fn($q) => $q->where('realm', $realm)),
			],
			// Staff profile básicos
			'work_phone'   => ['nullable', 'string', 'max:50'],
			'notes_admin'  => ['nullable', 'string', 'max:10000'],
			// Revocar sesiones (opcional)
			'revoke_sessions' => ['nullable', 'boolean'],
		];

		// Roles: solo validamos si el actor puede asignarlos
		if ($canAssignRoles)
		{
			$allRoles = Role::query()
					->where('guard_name', $realm)
					->pluck('name')
					->all();

			$rules['roles']	  = ['nullable', 'array'];
			$rules['roles.*'] = ['string', Rule::in($allRoles)];
		}

		// Comisiones: solo si puede editarlas
		if ($canEditCommissions)
		{
			$rules['commission_regular_first_year_pct'] = ['nullable', 'numeric', 'min:0', 'max:100'];
			$rules['commission_regular_renewal_pct']	= ['nullable', 'numeric', 'min:0', 'max:100'];
			$rules['commission_capitados_pct']			= ['nullable', 'numeric', 'min:0', 'max:100'];
		}

		// Validar inputs
		$data = $request->validate($rules);

		// Si edita comisiones, validamos obligatoriedad según roles vendedor_*
		if ($canEditCommissions)
		{
			// Si puede asignar roles, usamos los nuevos; si no, los roles actuales del usuario
			$rolesForValidation = $canAssignRoles ? collect($data['roles'] ?? []) : $user->getRoleNames();

			$this->validateVendorCommissions($rolesForValidation, $request);
		}

		// Persistir usuario
		$user->fill([
			'first_name'   => $data['first_name'],
			'last_name'	   => $data['last_name'],
			'display_name' => $data['display_name'] ?? $user->display_name,
			'status'	   => $data['status'],
			'email'		   => $data['email'],
		])->save();

		// StaffProfile
		$profile			  = StaffProfile::firstOrNew(['user_id' => $user->id]);
		$profile->work_phone  = $data['work_phone'] ?? $profile->work_phone;
		$profile->notes_admin = $data['notes_admin'] ?? $profile->notes_admin;

		if ($canEditCommissions)
		{
			// Si vienen vacíos, los dejamos en null
			$profile->commission_regular_first_year_pct = $data['commission_regular_first_year_pct'] ?? null;
			$profile->commission_regular_renewal_pct	= $data['commission_regular_renewal_pct'] ?? null;
			$profile->commission_capitados_pct			= $data['commission_capitados_pct'] ?? null;
		}

		$profile->save();

		// Roles (solo si puede)
		if ($canAssignRoles)
		{
			$roles = (array) ($data['roles'] ?? []);
			$user->syncRoles($roles);  // ← Sin permisos directos. Todo por roles.
		}

		// Revocar sesiones (si aplica)
		if (($request->boolean('revoke_sessions') === true) && \Gate::allows('sessions.revoke'))
		{
			try
			{
				$currentId = session()->getId();
				$query	   = \DB::table('sessions')->where('user_id', $user->id);
				if (auth()->id() === $user->id)
				{
					$query->where('id', '!=', $currentId);
				}
				$query->delete();
			}
			catch (\Throwable $e)
			{
				// opcional: log
			}
		}

		return redirect()
						->route('admin.users.edit', $user)
						->with('status', 'Usuario actualizado correctamente.');
	}

	/**
	 * DELETE /admin/users/{user}
	 * (Soft delete)
	 */
	public function destroy(User $user)
	{
		$this->authorize('delete', $user);

		$user->delete();

		session()->flash('status', __('Usuario eliminado.'));
		return redirect()->route('admin.users.index');
	}

	/**
	 * POST /admin/users/{id}/restore
	 */
	public function restore($id)
	{
		$user = User::withTrashed()->where('realm', 'admin')->findOrFail($id);
		$this->authorize('restore', $user);

		$user->restore();

		session()->flash('status', __('Usuario restaurado.'));
		return redirect()->route('admin.users.edit', $user);
	}

	/**
	 * PUT /admin/users/{user}/status
	 * Cambia status (active|suspended|locked) y revoca sesiones si corresponde.
	 */
	public function updateStatus(Request $request, User $user)
	{
		$this->authorize('update', $user);

		$data = $request->validate([
			'status' => ['required', 'in:active,suspended,locked'],
		]);

		$user->status = $data['status'];
		$user->save();

		// Revocar sesiones si no queda active
		if ($user->status !== 'active')
		{
			try
			{
				DB::table('sessions')->where('user_id', $user->id)->delete();
			}
			catch (Throwable $e)
			{
				
			}
		}

		session()->flash('status', __('Estado actualizado.'));
		return redirect()->route('admin.users.edit', $user);
	}

	/**
	 * PUT /admin/users/{user}/roles
	 */
	public function updateRoles(Request $request, User $user)
	{
		// Si tienes ability específica, cámbiala por 'updateRoles'
		$this->authorize('update', $user);

		$data = $request->validate([
			'roles'	  => ['array'],
			'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'admin')],
		]);

		$roles = collect($data['roles'] ?? []);

		// Validación de comisiones si aplica (cuando venga todo en un mismo form via AJAX)
		$this->validateVendorCommissions($roles, $request);

		$user->syncRoles($roles->all());

		session()->flash('status', __('Roles actualizados.'));
		return redirect()->route('admin.users.edit', $user);
	}

	/**
	 * POST /admin/users/{user}/password/reset-link
	 * Envía link de reset de contraseña al usuario.
	 */
	public function sendResetLink(User $user)
	{
		$this->authorize('update', $user);

		$status = Password::broker('admin')->sendResetLink(
				['email' => $user->email, 'realm' => 'admin'],
				function ($user, string $token)
				{
					// Usamos tus notificaciones propias por realm (ya existentes):
					$url		  = route('admin.password.reset', ['token' => $token, 'email' => $user->email]);
					$notification = new ResetPasswordAdmin(
							$url,
							minutes: config('auth.passwords.admin.expire', 30)
					);

					$async = config('notify.password_reset.async');
					if ($async === null)
					{
						$async = config('notify.mail.async');
					}
					$async ? $user->notify($notification) : $user->notifyNow($notification);
				}
		);

		if ($status === Password::RESET_LINK_SENT)
		{
			session()->flash('status', __($status));
		}
		else
		{
			session()->flash('error', __($status));
		}

		return redirect()->route('admin.users.edit', $user);
	}

	/**
	 * DELETE /admin/users/{user}/sessions
	 * Revoca todas las sesiones del usuario.
	 */
	public function revokeSessions(Request $request, User $user)
	{
		// La autorización ya la tienes en el middleware de la ruta.
		// (Opcional, redundante) $this->authorize('update', $user);

		$currentSessionId = $request->session()->getId();

		try
		{
			$query = DB::table('sessions')->where('user_id', $user->id);

			// Si estoy revocando mis propias sesiones, excluir la sesión actual
			if (Auth::id() === (int) $user->id)
			{
				$query->where('id', '!=', $currentSessionId);
			}

			$revoked = (int) $query->delete();

			$msg = Auth::id() === (int) $user->id ? __('Se revocaron :n sesiones de tu cuenta (se mantuvo la sesión actual).', ['n' => $revoked]) : __('Se revocaron :n sesiones del usuario.', ['n' => $revoked]);

			// Respuesta AJAX
			if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest')
			{
				return response()->json([
							'ok'	  => true,
							'message' => $msg,
							'revoked' => $revoked,
				]);
			}

			// Respuesta clásica (no-AJAX)
			return back()->with('status', $msg);
		}
		catch (Throwable $e)
		{
			$errorMsg = __('No se pudieron revocar las sesiones.');

			if ($request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest')
			{
				return response()->json([
							'ok'	  => false,
							'message' => $errorMsg,
								], 500);
			}

			return back()->with('error', $errorMsg);
		}
	}

	/**
	 * Valida que, si el usuario tiene rol vendedor_*, los % correspondientes sean obligatorios.
	 */
	private function validateVendorCommissions($roles, Request $request): void
	{
		$needsRegular = $roles->contains('vendedor_regular');
		$needsCap	  = $roles->contains('vendedor_capitados');

		$errors = [];

		if ($needsRegular)
		{
			if ($request->input('commission_regular_first_year_pct', '') === '')
			{
				$errors['commission_regular_first_year_pct'] = __('Obligatorio para vendedor regular.');
			}
			if ($request->input('commission_regular_renewal_pct', '') === '')
			{
				$errors['commission_regular_renewal_pct'] = __('Obligatorio para vendedor regular.');
			}
		}

		if ($needsCap)
		{
			if ($request->input('commission_capitados_pct', '') === '')
			{
				$errors['commission_capitados_pct'] = __('Obligatorio para vendedor capitados.');
			}
		}

		if (!empty($errors))
		{
			back()->withErrors($errors)->throwResponse();
		}
	}

	public function impersonate(Request $request, User $user): RedirectResponse
	{
		$actor = Auth::guard('admin')->user();
		abort_unless($actor, 403, 'No autenticado en guard admin.');

		// Autorización básica por rol (si usas Policy puedes reemplazar por $this->authorize('impersonate', $user);)
		if (!$actor->hasAnyRole(['admin', 'superadmin']))
		{
			abort(403, 'No autorizado para impersonar.');
		}

		if ((int) $actor->id === (int) $user->id)
		{
			return back()->with('error', 'No puedes impersonarte a ti mismo.');
		}

		if ($user->realm !== 'admin')
		{
			abort(403, 'Solo se permite impersonar usuarios del realm admin.');
		}

		if ($user->status !== 'active')
		{
			return back()->with('error', 'No puedes impersonar a un usuario que no está activo.');
		}

		// Restringe impersonar superadmin a solo superadmin
		if ($user->hasRole('superadmin') && !$actor->hasRole('superadmin'))
		{
			abort(403, 'Solo un superadmin puede impersonar a otro superadmin.');
		}

		// Si ya estás impersonando, primero salimos
		if ($request->session()->has('impersonator_id'))
		{
			// opcional: restaurar por si quedó colgado
			$this->stopImpersonation($request);
		}

		// Guarda datos del actor en sesión para poder volver
		$request->session()->put('impersonator_id', $actor->id);
		$request->session()->put('impersonator_email', $actor->email);
		$request->session()->put('impersonated_id', $user->id);
		$request->session()->put('impersonated_email', $user->email);
		$request->session()->put('impersonated_started_at', now()->toDateTimeString());

		// Hacemos login como el usuario destino EN EL MISMO GUARD admin
		Auth::guard('admin')->login($user);

		// Auditoría (no asumo tabla: dejo try/catch para no romper)
		try
		{
			Log::info('Impersonation started', [
				'actor_id'	 => $actor->id,
				'target_id'	 => $user->id,
				'ip'		 => $request->ip(),
				'user_agent' => $request->userAgent(),
			]);
		}
		catch (Throwable $e)
		{
			// no-op
		}

		return redirect()
						->route('admin.home')
						->with('status', "Ahora estás impersonando a {$user->email}.");
	}

	/**
	 * Finaliza impersonación y regresa a la sesión original.
	 */
	public function stopImpersonation(Request $request): RedirectResponse
	{
		$impersonatorId = (int) $request->session()->pull('impersonator_id');
		$request->session()->forget([
			'impersonator_email',
			'impersonated_id',
			'impersonated_email',
			'impersonated_started_at',
		]);

		if (!$impersonatorId)
		{
			// Nada que restaurar
			return redirect()->route('admin.home')->with('info', 'No estabas en impersonación.');
		}

		// Volvemos a loguear al actor original
		Auth::guard('admin')->loginUsingId($impersonatorId);

		try
		{
			Log::info('Impersonation stopped', [
				'actor_restored_id' => $impersonatorId,
				'ip'				=> $request->ip(),
				'user_agent'		=> $request->userAgent(),
			]);
		}
		catch (Throwable $e)
		{
			// no-op
		}

		return redirect()->route('admin.users.index')->with('status', 'Has vuelto a tu sesión.');
	}
}
