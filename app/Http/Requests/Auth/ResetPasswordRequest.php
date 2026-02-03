<?php
namespace App\Http\Requests\Auth;

use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        $policy = app(PasswordPolicy::class);

        // Contexto disponible (si el user ya existe):
        $ctx = [
            'first_name'   => $this->user()?->first_name,
            'last_name'    => $this->user()?->last_name,
            'display_name' => $this->user()?->display_name,
            'email'        => $this->user()?->email ?? $this->input('email'),
        ];

        return [
            'token'                 => ['required'],
            'email'                 => ['required','email'],
            'password'              => array_merge(['required','confirmed'], $policy->rule($ctx)),
            'password_confirmation' => ['required'],
        ];
    }
}
