<?php
//routes/admin/auth/business_units.php
use App\Http\Controllers\Admin\BusinessUnitApiController;
use App\Http\Controllers\Admin\BusinessUnitController;
use Illuminate\Support\Facades\Route;

// Prefijo /admin para las rutas, prefijo .admin para los nombres, middleware de autenticación/realm heredado
Route::prefix('business-units')->name('business-units.')->group(function ()
{
	// Vistas (listas de raíces)
	Route::get('consolidators', [BusinessUnitController::class, 'indexConsolidators'])->name('consolidators.index');
	Route::get('offices', [BusinessUnitController::class, 'indexOffices'])->name('offices.index');
	Route::get('freelancers', [BusinessUnitController::class, 'indexFreelancers'])->name('freelancers.index');

	// Vista: unidad (acceso por membresía o global)
	Route::get('{unit}', [BusinessUnitController::class, 'show'])->name('show');

	// API
	Route::prefix('api')->name('api.')->group(function ()
	{
		// List / Create
		Route::get('units', [BusinessUnitApiController::class, 'list'])->name('units');
		Route::post('units', [BusinessUnitApiController::class, 'store'])->name('units.store');

		// Read
		Route::get('units/{unit}', [BusinessUnitApiController::class, 'show'])->name('units.show');
		Route::get('units/{unit}/children', [BusinessUnitApiController::class, 'children'])->name('units.children');

		// Update
		Route::patch('units/{unit}/basic', [BusinessUnitApiController::class, 'updateBasic'])->name('units.basic.update');
		Route::patch('units/{unit}/status', [BusinessUnitApiController::class, 'updateStatus'])->name('units.status.update');

		// Global-only
		Route::post('units/{unit}/change-type', [BusinessUnitApiController::class, 'changeType'])->name('units.change_type');
		Route::post('units/{unit}/move', [BusinessUnitApiController::class, 'move'])->name('units.move');

		// Branding
		Route::post('units/{unit}/branding', [BusinessUnitApiController::class, 'updateBranding'])->name('units.branding.update');

		// Members
		Route::get('units/{unit}/members', [BusinessUnitApiController::class, 'members'])->name('units.members.list');
		Route::post('units/{unit}/members', [BusinessUnitApiController::class, 'memberLink'])->name('units.members.link');
		Route::patch('units/{unit}/members/{membership}', [BusinessUnitApiController::class, 'memberUpdateRole'])->name('units.members.update_role');
		Route::delete('units/{unit}/members/{membership}', [BusinessUnitApiController::class, 'memberRemove'])->name('units.members.remove');

		// Usuarios activos (solo si unit.members.invite es global)
		Route::get('users/active', [BusinessUnitApiController::class, 'usersSearchActive'])->name('users.active');

		Route::post('units/{unit}/members/create-user', [BusinessUnitApiController::class, 'memberCreateUser'])->name('units.members.create_user');

		Route::patch('units/{unit}/members/{membership}/status', [BusinessUnitApiController::class, 'memberUpdateStatus'])->name('units.members.update_status');

		// Roles scope=unit
		Route::get('roles/unit', [BusinessUnitApiController::class, 'rolesUnitScope'])->name('roles.unit');
		
		
        // --- Comisiones GSA por unidad ---
		// Comisiones GSA
		Route::get('units/{unit}/gsa-commissions', [BusinessUnitApiController::class, 'gsaCommissions'])->name('units.gsa_commissions.index');
		Route::get('units/{unit}/gsa-commissions/available', [BusinessUnitApiController::class, 'gsaCommissionsAvailable'])->name('units.gsa_commissions.available');
		Route::post('units/{unit}/gsa-commissions', [BusinessUnitApiController::class, 'gsaCommissionsStore'])->name('units.gsa_commissions.store');
		Route::patch('units/{unit}/gsa-commissions/{commissionUser}', [BusinessUnitApiController::class, 'gsaCommissionsUpdate'])->name('units.gsa_commissions.update');
		Route::delete('units/{unit}/gsa-commissions/{commissionUser}', [BusinessUnitApiController::class, 'gsaCommissionsDestroy'])->name('units.gsa_commissions.destroy');

		
	});
});
