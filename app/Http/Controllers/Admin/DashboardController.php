<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        // Si más adelante quieres métricas rápidas, pásalas aquí a la vista.
        return view('admin.dashboard', [
            'title' => __('Dashboard'),
        ]);
    }
}
