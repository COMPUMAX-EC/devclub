<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/panel', fn() => view('customer.home'))->name('dashboard');

Route::get('/password/force',  [PasswordController::class, 'forceEdit'])->name('password.force.edit');
Route::post('/password/force', [PasswordController::class, 'forceUpdate'])->name('password.force.update');