<?php

namespace App\Policies;

use App\Models\User;

class AdminUserPolicy
{
    /**
     * Si el actor tiene la identidad “superadmin” (p. ej. capacidad elevada),
     * le permitimos todo dentro del realm admin.
     * Retorna true para autorizar, null para continuar con el método específico.
     */
    public function before(User $actor, string $ability)
    {
        if ($actor->realm === 'admin' && $actor->can('rbac.superadmin.identity')) {
            return true;
        }
        return null;
    }

    /** LISTAR */
    public function viewAny(User $actor): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.viewAny');
    }

    /** VER UNO */
    public function view(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.view');
    }

    /** CREAR */
    public function create(User $actor): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.create');
    }

    /** ACTUALIZAR CAMPOS GENERALES */
    public function update(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.update');
    }

    /** BORRADO LÓGICO (soft delete) */
    public function delete(User $actor, User $target): bool
    {
        return $actor->realm === 'admin'
            && $actor->can('users.delete')
            && $actor->id !== $target->id; // no permitir borrarse a sí mismo
    }

    /** RESTAURAR (desde papelera) */
    public function restore(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.restore');
    }

    /** BORRADO FORZADO (hard delete) */
    public function forceDelete(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.forceDelete');
    }

    /* === Capacidades finas (campos/acciones específicas) === */

    /** Cambiar email */
    public function updateEmail(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.email.update');
    }

    /** Asignar roles (solo roles; sin permisos directos a usuarios) */
    public function assignRoles(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.roles.assign');
    }

    /** Asignar/quitar rol superadmin (si lo manejan separado) */
    public function assignSuperadmin(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.roles.assign-superadmin');
    }

    /** Editar comisiones */
    public function updateCommissions(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.commissions.edit');
    }

    /** Cambiar estatus (active/locked/suspended) */
    public function updateStatus(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.status.update');
    }

    /** Impersonar */
    public function impersonate(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false; // no impersonarse a sí mismo
        }
        return $actor->realm === 'admin' && $actor->can('users.impersonate');
    }

    /** Revocar sesiones (del target) */
    public function revokeSessions(User $actor, User $target): bool
    {
        // Permitimos revocar también las propias (el controlador preserva la sesión actual)
        return $actor->realm === 'admin' && $actor->can('users.sessions.revoke');
    }

    /** Enviar email de reset de contraseña (al target) */
    public function sendPasswordReset(User $actor, User $target): bool
    {
        return $actor->realm === 'admin' && $actor->can('users.password.reset.send');
    }
}
