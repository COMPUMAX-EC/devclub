<?php
// routes/public/files.php
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Archivos (catálogo central)
// -------------------------------------------------------------------------

// Descarga "normal" no firmada usando UUID
Route::get('/files/{uuid}', [FileController::class, 'showByUuid'])
		->name('files.show');

Route::get('/files/temp/{file}', [FileController::class, 'showTemporary'])
		->name('files.temp');
