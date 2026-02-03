<?php

use App\Http\Controllers\Admin\CapitatedContractController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.do');

Route::get('/forgot-password', [PasswordController::class, 'requestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordController::class, 'emailLink'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordController::class, 'reset'])->name('password.update');
