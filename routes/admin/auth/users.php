<?php
// routes/admin/auth/users.php
// Este archivo esta esta con prefijo /admin para las rutas, prefijo .admin para los nombres, middleware de autenticación/realm heredado

use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;


Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
Route::post('/logout', [LoginController::class, 'logout']);

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

// -------------------------
// Users CRUD
// -------------------------
Route::get('/users', [UsersController::class, 'index'])->name('users.index');
Route::get('/users/create', [UsersController::class, 'create'])->name('users.create');
Route::post('/users', [UsersController::class, 'store'])->name('users.store');

// -------------------------
// API búsqueda genérica (para UiUserSearchSelector & co.)
// -------------------------
Route::get('/users/api/search', [UsersController::class, 'apiSearch'])
    ->name('users.api.search')
    ->middleware('can:users.viewAny');

// Importante: definir "show" y "edit" después de "create" para evitar colisiones
Route::get('/users/{user}', [UsersController::class, 'show'])->name('users.show')->whereNumber('user');
Route::get('/users/{user}/edit', [UsersController::class, 'edit'])->name('users.edit')->whereNumber('user');
Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update')->whereNumber('user');
Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy')->whereNumber('user'); // soft delete

// -------------------------
// Acciones adicionales
// -------------------------
Route::post('/users/{user}/restore', [UsersController::class, 'restore'])->name('users.restore')->whereNumber('user');
Route::post('/users/{user}/impersonate', [UsersController::class, 'impersonate'])->name('users.impersonate')->whereNumber('user');
Route::post('/users/{user}/sessions/revoke', [UsersController::class, 'revokeSessions'])
    ->name('users.sessions.revoke')
    ->middleware([
        'can:users.sessions.revoke', // capacidad global
        'can:update,user',           // alcance sobre el usuario destino
    ]);
Route::post('/users/{user}/send-reset', [UsersController::class, 'sendReset'])->name('users.send-reset')->whereNumber('user');
Route::post('/users/{user}/lock', [UsersController::class, 'lock'])->name('users.lock')->whereNumber('user');
Route::post('/users/{user}/unlock', [UsersController::class, 'unlock'])->name('users.unlock')->whereNumber('user');

Route::post('/users/{user}/impersonate', [UsersController::class, 'impersonate'])->name('users.impersonate'); // POST /admin/users/{user}/impersonate

Route::post('/impersonate/stop', [UsersController::class, 'stopImpersonation'])->name('impersonate.stop'); // POST /admin/impersonate/stop


Route::get('/password/force',  [PasswordController::class, 'forceEdit'])->name('password.force.edit');
Route::post('/password/force', [PasswordController::class, 'forceUpdate'])->name('password.force.update');
