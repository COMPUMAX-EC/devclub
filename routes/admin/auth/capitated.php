<?php
// routes/admin/auth/capitated.php

use App\Http\Controllers\Admin\CapitatedBatchController;
use App\Http\Controllers\Admin\CapitatedContractController;
use App\Http\Controllers\Admin\CapitatedPersonController;
use App\Http\Controllers\Admin\CapitatedMonthlyReportController;
use Illuminate\Support\Facades\Route;

// Prefijo /admin, middleware 'admin' y nombre base 'admin.' vienen del grupo padre en routes/web.php

Route::prefix('companies/{company}/capitados')
    ->name('companies.capitated.')
    ->group(function () {
        // Suscripciones (contratos)
        Route::get('/contracts', [CapitatedContractController::class, 'index'])
            ->name('contracts.index');
        Route::get('/contracts/{contract}', [CapitatedContractController::class, 'show'])
            ->name('contracts.show');

        // Personas (fichas asegurado por producto)
        Route::get('/persons', [CapitatedPersonController::class, 'index'])
            ->name('persons.index');
        Route::get('/persons/{person}', [CapitatedPersonController::class, 'show'])
            ->name('persons.show');

        // Batches
        Route::get('/batches', [CapitatedBatchController::class, 'index'])
            ->name('batches.index');

        // IMPORTANTE: va ANTES de /batches/{batch} para no colisionar con el parámetro {batch}
        Route::get('/batches/template', [CapitatedBatchController::class, 'template'])
            ->name('batches.template');

        // Detalle de batch (JSON para la vista de detalle)
        Route::get('/batches/{batch}', [CapitatedBatchController::class, 'show'])
            ->name('batches.show');

        // Items del batch
        Route::get('/batches/{batch}/items', [CapitatedBatchController::class, 'items'])
            ->name('batches.items');

        // Registros mensuales generados por el batch
        Route::get('/batches/{batch}/monthly-records', [CapitatedBatchController::class, 'monthlyRecords'])
            ->name('batches.monthly_records.index');

        // Rollback completo del batch
        Route::post('/batches/{batch}/rollback', [CapitatedBatchController::class, 'rollback'])
            ->name('batches.rollback');

        // Rollback de un registro mensual concreto del batch
        Route::post('/batches/{batch}/monthly-records/{record}/rollback', [CapitatedBatchController::class, 'rollbackMonthlyRecord'])
            ->name('batches.monthly_records.rollback');

        // Upload Excel
        Route::post('/batches/upload', [CapitatedBatchController::class, 'upload'])
            ->name('batches.upload');
		
		Route::get('/reportes/mensuales', [CapitatedMonthlyReportController::class, 'months'])
			->name('reporte.mensual.months');

		Route::get('/reportes/mensuales/{month}/download', [CapitatedMonthlyReportController::class, 'download'])
			->name('reporte.mensual.download');
    });
