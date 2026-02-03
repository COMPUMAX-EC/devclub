<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.companies.manage') === true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'array'],
            'name.es'  => ['required', 'string', 'max:255'],
            'name.en'  => ['required', 'string', 'max:255'],

            'short_code' => [
                'required',
                'string',
                'min:3',
                'max:5',
                'regex:/^[A-Za-z]+$/',
                'unique:companies,short_code',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name.es'     => 'nombre (ES)',
            'name.en'     => 'nombre (EN)',
            'short_code'  => 'código único',
        ];
    }
}
