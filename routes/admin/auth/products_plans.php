<?php
// /routes/admin/auth/products_plans.php
use App\Http\Controllers\Admin\PlanVersionAgeSurchargeController;
use App\Http\Controllers\Admin\PlanVersionController;
use App\Http\Controllers\Admin\PlanVersionCoverageController;
use App\Http\Controllers\Admin\PlanVersionCountryController;
use App\Http\Controllers\Admin\PlanVersionRepatriationCountryController;
use Illuminate\Support\Facades\Route;

// Prefijo /admin para las rutas, prefijo .admin para los nombres, middleware de autenticación/realm heredado
Route::middleware('can:admin.products.manage')
    ->prefix('products')
    ->name('products.')
    ->group(function () {

        // Gestión de versiones de planes por producto
        Route::get('/{product}/plans', [PlanVersionController::class, 'index'])
            ->name('plans.index');

        Route::post('/{product}/plans', [PlanVersionController::class, 'store'])
            ->name('plans.store');

        Route::post('/{product}/plans/{planVersion}/clone', [PlanVersionController::class, 'clone'])
            ->name('plans.clone');

        Route::get('/{product}/plans/{planVersion}/edit', [PlanVersionController::class, 'edit'])
            ->name('plans.edit');

        Route::put('/{product}/plans/{planVersion}', [PlanVersionController::class, 'update'])
            ->name('plans.update');

        Route::delete('/{product}/plans/{planVersion}', [PlanVersionController::class, 'destroy'])
            ->name('plans.destroy');

        Route::get('/{product}/plans/{planVersion}/pdf', [PlanVersionController::class, 'pdf_preview'])
            ->name('plans.pdf');

		// Términos HTML (ES / EN) de la versión
        Route::get(
            '/{product}/plans/{planVersion}/terms-html',
            [PlanVersionController::class, 'showTermsHtml']
        )->name('plans.terms-html.show');

		Route::patch(
            '/{product}/plans/{planVersion}/terms-html',
            [PlanVersionController::class, 'updateTermsHtml']
        )->name('plans.terms-html.update');

        // Coberturas asociadas a una versión de plan
        Route::get(
            '/{product}/plans/{planVersion}/coverages/available',
            [PlanVersionCoverageController::class, 'available']
        )->name('plans.coverages.available');

        Route::post(
            '/{product}/plans/{planVersion}/coverages',
            [PlanVersionCoverageController::class, 'store']
        )->name('plans.coverages.store');

        Route::delete(
            '/{product}/plans/{planVersion}/coverages/{coverage}',
            [PlanVersionCoverageController::class, 'destroy']
        )->name('plans.coverages.destroy');

        Route::post(
            '/{product}/plans/{planVersion}/coverages/reorder',
            [PlanVersionCoverageController::class, 'reorder']
        )->name('plans.coverages.reorder');

        Route::patch(
            '/{product}/plans/{planVersion}/coverages/{coverage}',
            [PlanVersionCoverageController::class, 'updateValue']
        )->name('plans.coverages.update');

        // Países y tarifas por versión de plan
        Route::get(
            '/{product}/plans/{planVersion}/countries',
            [PlanVersionCountryController::class, 'index']
        )->name('plans.countries.index');

        Route::post(
            '/{product}/plans/{planVersion}/countries',
            [PlanVersionCountryController::class, 'store']
        )->name('plans.countries.store');

        Route::post(
            '/{product}/plans/{planVersion}/countries/attach-zone',
            [PlanVersionCountryController::class, 'attachZone']
        )->name('plans.countries.attach-zone');

        Route::patch(
            '/{product}/plans/{planVersion}/countries/{country}',
            [PlanVersionCountryController::class, 'update']
        )->name('plans.countries.update');

        Route::delete(
            '/{product}/plans/{planVersion}/countries/{country}',
            [PlanVersionCountryController::class, 'destroy']
        )->name('plans.countries.destroy');

        Route::post(
            '/{product}/plans/{planVersion}/countries/detach-by-zone',
            [PlanVersionCountryController::class, 'detachByZone']
        )->name('plans.countries.detach-by-zone');

        // Países permitidos para repatriación por versión de plan
        Route::get(
            '/{product}/plans/{planVersion}/repatriation-countries',
            [PlanVersionRepatriationCountryController::class, 'index']
        )->name('plans.repatriation-countries.index');

        Route::post(
            '/{product}/plans/{planVersion}/repatriation-countries',
            [PlanVersionRepatriationCountryController::class, 'store']
        )->name('plans.repatriation-countries.store');

        Route::post(
            '/{product}/plans/{planVersion}/repatriation-countries/attach-zone',
            [PlanVersionRepatriationCountryController::class, 'attachZone']
        )->name('plans.repatriation-countries.attach-zone');

        Route::delete(
            '/{product}/plans/{planVersion}/repatriation-countries/{country}',
            [PlanVersionRepatriationCountryController::class, 'destroy']
        )->name('plans.repatriation-countries.destroy');

        Route::post(
            '/{product}/plans/{planVersion}/repatriation-countries/detach-by-zone',
            [PlanVersionRepatriationCountryController::class, 'detachByZone']
        )->name('plans.repatriation-countries.detach-by-zone');

        // Recargos por rango de edad de una versión de plan
        Route::get(
            '/{product}/plans/{planVersion}/age-surcharges',
            [PlanVersionAgeSurchargeController::class, 'index']
        )->name('plans.age-surcharges.index');

        Route::post(
            '/{product}/plans/{planVersion}/age-surcharges',
            [PlanVersionAgeSurchargeController::class, 'store']
        )->name('plans.age-surcharges.store');

        Route::patch(
            '/{product}/plans/{planVersion}/age-surcharges/{ageSurcharge}',
            [PlanVersionAgeSurchargeController::class, 'update']
        )->name('plans.age-surcharges.update');

        Route::delete(
            '/{product}/plans/{planVersion}/age-surcharges/{ageSurcharge}',
            [PlanVersionAgeSurchargeController::class, 'destroy']
        )->name('plans.age-surcharges.destroy');
    });
