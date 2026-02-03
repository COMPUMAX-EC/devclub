<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    #[\Override]
    protected function prepareForValidation(): void
    {
        $this->merge([
            'subscripcion' => $this->boolean('subscripcion'),
            'capitado'     => $this->boolean('capitado'),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('user')->id;

        return [
            'name'         => ['required','string','max:255'],
            'email'        => ['required','email','max:255', Rule::unique('users','email')->ignore($id)],
            'password'     => ['nullable','string','min:8','confirmed'],
            'status'       => ['required','in:active,suspended'],
            'subscripcion' => ['required','boolean'], // 👈
            'capitado'     => ['required','boolean'], // 👈
        ];
    }
}
