<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;

class ForcedPasswordController extends Controller
{
    public function edit(Request $request)
    {
        $realm = realm();
        $view  = "{$realm}.auth.first_password_change";

        return view($view);
    }

    public function update(Request $request, PasswordPolicy $policy)
    {
        $user = $request->user();

        $rules = [
            'password'              => array_merge(['required','confirmed','string'], $policy->rule([
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'display_name' => $user->display_name,
                'email'        => $user->email,
            ])),
            'password_confirmation' => ['required','string'],
        ];

        $data = $request->validate($rules);

        $user->forceFill([
            'password'              => $data['password'],
            'force_password_change' => false,
        ])->save();

        // Regenerar sesión para seguridad
        $request->session()->regenerate();

        // Redirigir al dashboard según realm
        $dashboard = $user->realm === 'admin' ? 'admin.home' : 'customer.home';

        return redirect()->route($dashboard)->with('status', __('Contraseña actualizada.'));
    }
}
