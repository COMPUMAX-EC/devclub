<?php
// /routes/admin/auth/templates.php

use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TemplateVersionController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:admin.templates.edit')
	->prefix('templates')
	->name('templates.')
	->group(function () {

		Route::get('/', [TemplateController::class, 'index'])
			->name('index');

		Route::post('/', [TemplateController::class, 'store'])
			->name('store');

		// JSON show (data fresca)
		Route::get('/{template}', [TemplateController::class, 'show'])
			->name('show');

		Route::get('/{template}/edit', [TemplateController::class, 'edit'])
			->name('edit');

		Route::patch('/{template}/basic', [TemplateController::class, 'updateBasic'])
			->name('basic.update');

		Route::patch('/{template}/test-data', [TemplateController::class, 'updateTestData'])
			->name('test-data.update');

		Route::delete('/{template}', [TemplateController::class, 'destroy'])
			->name('destroy');

		Route::post('/{template}/clone', [TemplateController::class, 'clone'])
			->name('clone');

		// Versions
		Route::post('/{template}/versions', [TemplateVersionController::class, 'store'])
			->name('versions.store');

		Route::get('/{template}/versions/{templateVersion}', [TemplateVersionController::class, 'show'])
			->name('versions.show');

		Route::patch('/{template}/versions/{templateVersion}/basic', [TemplateVersionController::class, 'updateBasic'])
			->name('versions.basic.update');

		Route::patch('/{template}/versions/{templateVersion}/test-data', [TemplateVersionController::class, 'updateTestData'])
			->name('versions.test-data.update');

		Route::post('/{template}/versions/{templateVersion}/activate', [TemplateVersionController::class, 'activate'])
			->name('versions.activate');

		Route::post('/{template}/versions/{templateVersion}/deactivate', [TemplateVersionController::class, 'deactivate'])
			->name('versions.deactivate');

		Route::post('/{template}/versions/{templateVersion}/clone', [TemplateVersionController::class, 'clone'])
			->name('versions.clone');

		Route::delete('/{template}/versions/{templateVersion}', [TemplateVersionController::class, 'destroy'])
			->name('versions.destroy');

		// Preview: por versión
		Route::get('/{template}/versions/{templateVersion}/pdf', [TemplateVersionController::class, 'previewVersionPdf'])
			->name('versions.preview.pdf');

		// Preview: versión activa
		Route::get('/{template}/active/pdf', [TemplateVersionController::class, 'previewActivePdf'])
			->name('active.preview.pdf');
	});
