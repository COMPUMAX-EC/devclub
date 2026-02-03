<?php
// routes/public/files.php


use App\Http\Controllers\Admin\CapitatedContractController;
use Illuminate\Support\Facades\Route;


Route::get('/contract/cap/{hash}', [CapitatedContractController::class, 'showPdfByUuid'])->name('capitated.contracts.show_pdf_by_uuid');