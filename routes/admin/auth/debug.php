<?php

use App\Http\Controllers\Admin\UserUnitsDebugController;
use App\Http\Controllers\Dev\CodeEditorPlaygroundController;

Route::middleware('can:debug.unit.permission')
->group(function () {
// Página que carga el .vue
Route::get('debug/user-units', [UserUnitsDebugController::class, 'page'])
		->name('debug.user-units');

// API lista de usuarios/unidades (JSON) – OJO: distinta URL
Route::get('debug/user-units/index', [UserUnitsDebugController::class, 'index'])
		->name('debug.user-units.index');

// API abilities para un user/unit concretos
Route::get('debug/user-units/abilities', [UserUnitsDebugController::class, 'abilities'])
		->name('debug.user-units.abilities');
});


Route::prefix('code-editor/')->name('tools.')->group(function () {
	Route::get('', [CodeEditorPlaygroundController::class, 'index'])->name('code-editor.index');
	Route::get('/{language}', [CodeEditorPlaygroundController::class, 'edit'])->name('code-editor.edit');
});
