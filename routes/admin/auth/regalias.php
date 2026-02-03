<?php
// routes/admin/auth/regalias.php
// Este archivo está con prefijo /admin para las rutas,
// prefijo .admin para los nombres, middleware de autenticación/realm heredado.

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\RegaliasController;

// -------------------------
// Vista principal Regalías
// -------------------------

Route::get('/regalias', [RegaliasController::class, 'index'])
    ->name('regalias.index')
    ->middleware('can:regalia.users.read');

// -------------------------
// Regalías – API
// -------------------------

// Listado paginado de beneficiarios + sus regalías
// GET /admin/regalias/api/beneficiaries
Route::get('/regalias/api/beneficiaries', [RegaliasController::class, 'beneficiariesIndex'])
    ->name('regalias.api.beneficiaries.index')
    ->middleware('can:regalia.users.read');

// Orígenes tipo usuario disponibles para un beneficiario (tab "Usuarios")
// GET /admin/regalias/api/beneficiaries/{beneficiary}/origins/users/available
Route::get(
    '/regalias/api/beneficiaries/{beneficiary}/origins/users/available',
    [RegaliasController::class, 'availableOriginsUsers']
)
    ->name('regalias.api.beneficiaries.origins.users.available')
    ->whereNumber('beneficiary')
    ->middleware('can:regalia.users.edit');

// Orígenes tipo unidad disponibles para un beneficiario (tab "Unidades")
// GET /admin/regalias/api/beneficiaries/{beneficiary}/origins/units/available
Route::get(
    '/regalias/api/beneficiaries/{beneficiary}/origins/units/available',
    [RegaliasController::class, 'availableOriginsUnits']
)
    ->name('regalias.api.beneficiaries.origins.units.available')
    ->whereNumber('beneficiary')
    ->middleware('can:regalia.users.edit');

// Crear regalía (se usa tanto desde tabs como desde cards, comisión = 0 por defecto)
// POST /admin/regalias/api/regalias
Route::post('/regalias/api/regalias', [RegaliasController::class, 'store'])
    ->name('regalias.api.regalias.store')
    ->middleware('can:regalia.users.edit');

// Actualizar comisión de una regalía
// PATCH /admin/regalias/api/regalias/{regalia}
Route::patch('/regalias/api/regalias/{regalia}', [RegaliasController::class, 'update'])
    ->name('regalias.api.regalias.update')
    ->whereNumber('regalia')
    ->middleware('can:regalia.users.edit');

// Eliminar regalía
// DELETE /admin/regalias/api/regalias/{regalia}
Route::delete('/regalias/api/regalias/{regalia}', [RegaliasController::class, 'destroy'])
    ->name('regalias.api.regalias.destroy')
    ->whereNumber('regalia')
    ->middleware('can:regalia.users.edit');
