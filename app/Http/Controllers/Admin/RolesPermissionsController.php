<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class RolesPermissionsController extends Controller
{
    /**
     * Página principal del administrador de roles/permisos para un guard.
     *
     * GET /admin/acl/roles-permissions/{guard}
     */
    public function index(string $guard)
    {
        $guardName = $this->normalizeGuard($guard);

        return view('admin.acl.roles', [
            'guard'  => $guardName,
            'locale' => app()->getLocale(),
        ]);
    }

    /**
     * Datos de la matriz de roles/permisos para un guard.
     *
     * Devuelve:
     * - roles: colección de roles del guard
     * - permissions: colección de permisos del guard
     * - matrix: arreglo [role_id => [permission_id, ...]]
     */
    public function matrixData(Request $request, string $guard): JsonResponse
    {
        $guardName = $this->normalizeGuard($guard);

        // Todos los roles del guard
        $roles = Role::query()
            ->where('guard_name', $guardName)
            ->orderBy('name')
            ->get();

        // Todos los permisos del guard (sin filtrar por prefijo: TODOS)
        $permissions = Permission::query()
            ->where('guard_name', $guardName)
            ->orderBy('name')
            ->get();

        // Pivot role_has_permissions
        $roleIds       = $roles->pluck('id')->all();
        $permissionIds = $permissions->pluck('id')->all();

        $pivotRows = DB::table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->get(['role_id', 'permission_id']);

        $matrix = [];

        foreach ($roles as $role) {
            $matrix[$role->id] = [];
        }

        foreach ($pivotRows as $row) {
            $matrix[$row->role_id][] = (int) $row->permission_id;
        }

        return response()->json([
            'roles'       => $roles,
            'permissions' => $permissions,
            'matrix'      => $matrix,
        ]);
    }

    /**
     * Crear un rol para el guard indicado.
     */
    public function storeRole(Request $request, string $guard): JsonResponse
    {
        $guardName = $this->normalizeGuard($guard);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where(fn ($q) => $q->where('guard_name', $guardName)),
            ],
            // label traducible (JSON) opcional
            'label' => ['nullable', 'array'],
            // scope opcional (system / consolidator / agency / freelance, etc.)
            'scope' => ['nullable', 'string', 'max:50'],
        ]);

        $role = new Role();
        $role->guard_name = $guardName;
        $role->name       = $data['name'];

        if (array_key_exists('label', $data)) {
            $role->label = $data['label']; // HasTranslatableJson se encarga del cast
        }

        if (array_key_exists('scope', $data)) {
            $role->scope = $data['scope'];
        }

        $role->save();

        return response()->json([
            'role'    => $role,
            'message' => 'Rol creado correctamente.',
        ], 201);
    }

    /**
     * Actualizar un rol del guard indicado.
     */
    public function updateRole(Request $request, string $guard, Role $role): JsonResponse
    {
        $guardName = $this->normalizeGuard($guard);

        if ($role->guard_name !== $guardName) {
            abort(404);
        }

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($q) => $q->where('guard_name', $guardName))
                    ->ignore($role->id),
            ],
            'label' => ['sometimes', 'nullable', 'array'],
            'scope' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        if (array_key_exists('name', $data)) {
            $role->name = $data['name'];
        }

        if (array_key_exists('label', $data)) {
            $role->label = $data['label'];
        }

        if (array_key_exists('scope', $data)) {
            $role->scope = $data['scope'];
        }

        $role->save();

        return response()->json([
            'role'    => $role,
            'message' => 'Rol actualizado correctamente.',
        ]);
    }

    /**
     * Crear un permiso para el guard indicado.
     */
    public function storePermission(Request $request, string $guard): JsonResponse
    {
        $guardName = $this->normalizeGuard($guard);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')->where(fn ($q) => $q->where('guard_name', $guardName)),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $permission = new Permission();
        $permission->guard_name  = $guardName;
        $permission->name        = $data['name'];
        $permission->description = $data['description'] ?? null;
        $permission->save();

        return response()->json([
            'permission' => $permission,
            'message'    => 'Permiso creado correctamente.',
        ], 201);
    }

    /**
     * Actualizar un permiso del guard indicado.
     */
    public function updatePermission(
        Request $request,
        string $guard,
        Permission $permission
    ): JsonResponse {
        $guardName = $this->normalizeGuard($guard);

        if ($permission->guard_name !== $guardName) {
            abort(404);
        }

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')
                    ->where(fn ($q) => $q->where('guard_name', $guardName))
                    ->ignore($permission->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        if (array_key_exists('name', $data)) {
            $permission->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $permission->description = $data['description'];
        }

        $permission->save();

        return response()->json([
            'permission' => $permission,
            'message'    => 'Permiso actualizado correctamente.',
        ]);
    }

    /**
     * Toggle (asignar / revocar) un permiso para un rol concreto.
     *
     * Se ejecuta por cada checkbox individual.
     */
    public function toggleAssignment(Request $request, string $guard): JsonResponse
    {
        $guardName = $this->normalizeGuard($guard);

        $data = $request->validate([
            'role_id'       => ['required', 'integer', 'exists:roles,id'],
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
            'value'       => ['required', 'boolean'],
        ]);

        // Rol del guard correcto
        /** @var \App\Models\Role $role */
        $role = Role::query()
            ->where('guard_name', $guardName)
            ->findOrFail($data['role_id']);

        // Modelo de permiso según config de Spatie
        $permissionClass = config('permission.models.permission');

        /** @var \Spatie\Permission\Contracts\Permission|\Illuminate\Database\Eloquent\Model $permission */
        $permission = $permissionClass::query()
            ->where('guard_name', $guardName)
            ->findOrFail($data['permission_id']);

        if ($data['value']) {
            // Asignar permiso si aún no lo tiene
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        } else {
            // Revocar permiso si lo tiene
            if ($role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
            }
        }

        return response()->json([
            'message' => 'Asignación rol/permisos actualizada correctamente.',
        ]);
    }

    /**
     * Normaliza y valida el guard.
     */
    protected function normalizeGuard(string $guard): string
    {
        $guard = trim($guard);

        if (! in_array($guard, ['admin', 'customer'], true)) {
            abort(404);
        }

        return $guard;
    }
}
