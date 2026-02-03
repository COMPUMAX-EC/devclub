<?php

use App\Http\Controllers\PdfTestController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dev\IpCountryTestController;
use App\Http\Controllers\Dev\Select2DemoController;
use App\Http\Controllers\FileTestController;

Route::get('/test/html', [PdfTestController::class, 'previewHtml'])->name('test.html');
Route::get('/test/pdf',  [PdfTestController::class, 'generatePdf'])->name('test.pdf');


Route::prefix('dev')->group(function () {
    Route::get('/ip-country', [IpCountryTestController::class, 'country'])
        ->name('dev.ip-country');

    Route::get('/ip-country/diagnostics', [IpCountryTestController::class, 'diagnostics'])
        ->name('dev.ip-country.diagnostics');
	
Route::get('/select2', [Select2DemoController::class, 'index'])
	->name('dev.select2-demo');
});


Route::get('/test/files', [FileTestController::class, 'index'])
    ->name('test.files.index');

Route::post('/test/files', [FileTestController::class, 'store'])
    ->name('test.files.store');

Route::post('/test/files/{file}/replace', [FileTestController::class, 'replace'])
    ->name('test.files.replace');

Route::delete('/test/files/{file}', [FileTestController::class, 'destroy'])
    ->name('test.files.destroy');


Route::get('/test/html', function () {
    return view('dev.input-html-test');
})->name('dev.input-html-test');
