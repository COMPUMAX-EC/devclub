<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    // 👇 Esto fuerza a que siempre existan las claves (0/1) aunque el checkbox no se envíe
    #[\Override]
protected function prepareForValidation(): void
    {
        $this->merge([
            'subscripcion' => $this->boolean('subscripcion'), // true/false
            'capitado'     => $this->boolean('capitado'),     // true/false
        ]);
    }

    public function rules(): array
    {
        return [
            'name'         => ['required','string','max:255'],
            'email'        => ['required','email','max:255','unique:users,email'],
            'password'     => ['required','string','min:8','confirmed'],
            'status'       => ['required','in:active,suspended'],
            'subscripcion' => ['required','boolean'], // 👈
            'capitado'     => ['required','boolean'], // 👈
        ];
    }
}
