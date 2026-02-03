<?php

use App\Http\Controllers\Admin\RolesPermissionsController;
use Illuminate\Support\Facades\Route;

// Este archivo ya está dentro de los middleware web y auth,
// y tiene prefijo .admin para los nombres y 'admin' para las rutas.

Route::prefix('acl')
    ->name('acl.')
    ->middleware(['can:system.roles'])
    ->group(function () {
        // Página principal por guard (admin / customer)
        Route::get('roles/{guard}', [RolesPermissionsController::class, 'index'])
            ->where('guard', 'admin|customer')
            ->name('roles-permissions.index');

        // Datos matriz roles/permisos
        Route::get('roles/{guard}/matrix', [RolesPermissionsController::class, 'matrixData'])
            ->where('guard', 'admin|customer')
            ->name('roles-permissions.matrix');

        // CRUD de roles (por guard)
        Route::post('roles/{guard}/roles', [RolesPermissionsController::class, 'storeRole'])
            ->where('guard', 'admin|customer')
            ->name('roles-permissions.roles.store');

        Route::put('roles/{guard}/roles/{role}', [RolesPermissionsController::class, 'updateRole'])
            ->where('guard', 'admin|customer')
            ->name('roles-permissions.roles.update');

        // CRUD de permisos (por guard)
        Route::post('roles/{guard}/permissions', [RolesPermissionsController::class, 'storePermission'])
            ->where('guard', 'admin|customer')
            ->name('roles-permissions.permissions.store');

        Route::put('roles/{guard}/permissions/{permission}', [RolesPermissionsController::class, 'updatePermission'])
            ->where('guard', 'admin|customer')
            ->name('roles-permissions.permissions.update');

        // Toggle individual rol-permiso (checkbox)
        Route::post('roles/{guard}/toggle', [RolesPermissionsController::class, 'toggleAssignment'])
            ->where('guard', 'admin|customer')
            ->name('roles-permissions.toggle');
    });
