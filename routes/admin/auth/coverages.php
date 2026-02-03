<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\CoverageCatalogController;

Route::middleware(['can:admin.coverages.manage']) // ajusta el permiso si usas otro
    ->prefix('coverages')
    ->name('coverages.')
    ->group(function () {
        /**
         * Página principal del catálogo de coberturas
         * (renderiza la vista con <AdminCoveragesIndex />)
         */
        Route::get('/', [CoverageCatalogController::class, 'index'])
            ->name('index');

        /*
        |--------------------------------------------------------------------------
        | CATEGORÍAS DE COBERTURAS
        |--------------------------------------------------------------------------
        */

        // Crear categoría
        Route::post('categories', [CoverageCatalogController::class, 'storeCategory'])
            ->name('categories.store');

        // Actualizar categoría
        Route::put('categories/{category}', [CoverageCatalogController::class, 'updateCategory'])
            ->name('categories.update');

        // Archivar categoría
        Route::post('categories/{category}/archive', [CoverageCatalogController::class, 'archiveCategory'])
            ->name('categories.archive');

        // Restaurar categoría
        Route::post('categories/{category}/restore', [CoverageCatalogController::class, 'restoreCategory'])
            ->name('categories.restore');

        // Listar categorías archivadas (para el modal)
        Route::get('categories/archived', [CoverageCatalogController::class, 'archivedCategories'])
            ->name('categories.archived');

        // Reordenar coberturas dentro de una categoría (drag&drop)
        Route::post('categories/{category}/reorder', [CoverageCatalogController::class, 'reorderCoverages'])
            ->name('categories.reorder');

        /*
        |--------------------------------------------------------------------------
        | ÍTEMS (COBERTURAS)
        |--------------------------------------------------------------------------
        */

        // Crear cobertura
        Route::post('items', [CoverageCatalogController::class, 'storeCoverage'])
            ->name('items.store');

        // Actualizar cobertura
        Route::put('items/{coverage}', [CoverageCatalogController::class, 'updateCoverage'])
            ->name('items.update');

        // Archivar cobertura
        Route::post('items/{coverage}/archive', [CoverageCatalogController::class, 'archiveCoverage'])
            ->name('items.archive');

        // Restaurar cobertura
        Route::post('items/{coverage}/restore', [CoverageCatalogController::class, 'restoreCoverage'])
            ->name('items.restore');

        // Eliminar cobertura (si no está en uso, etc.)
        Route::delete('items/{coverage}', [CoverageCatalogController::class, 'destroyCoverage'])
            ->name('items.destroy');

        // Ver usos de una cobertura (modal "Ver usos")
        Route::get('items/{coverage}/usages', [CoverageCatalogController::class, 'coverageUsages'])
            ->name('items.usages');
    });
