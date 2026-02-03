<?php

use App\Http\Controllers\Auth\PasswordController;

Route::get('/password-policy', [PasswordController::class, 'policy'])->name('api.password.policy');
Route::post('/password-check', [PasswordController::class, 'check'])->name('api.password.check');
