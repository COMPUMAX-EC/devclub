<?php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');

Route::middleware('web')->group(function ()
{
	foreach (glob(__DIR__.'/public/*.php') as $filename)
	{
		require_once $filename;
	}
});




// ===== admin (/admin) =====
Route::middleware('admin')
->prefix('admin')
->name('admin.')
->group(function ()
{
	foreach (glob(__DIR__.'/admin/auth/*.php') as $filename)
	{
		require_once $filename;
	}
});


Route::middleware('admin_guest')
->prefix('admin')
->name('admin.')
->group(function () {
	foreach (glob(__DIR__.'/admin/public/*.php') as $filename)
	{
		require_once $filename;
	}
});

// ===== Customer (/) =====
// Autenticados (portal cliente)
Route::middleware('customer')
->prefix('customer')
->name('customer.')
->group(function ()
{
	foreach (glob(__DIR__.'/customer/auth/*.php') as $filename)
	{
		require_once $filename;
	}
});

// Invitados (login/registro) — incluye 'web' para $errors
Route::middleware('customer_guest')
->prefix('customer')
->name('customer.')
->group(function () {
	foreach (glob(__DIR__.'/customer/public/*.php') as $filename)
	{
		require_once $filename;
	}
});

// Fallback genérico
Route::fallback(fn() => abort(404));

