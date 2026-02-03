<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin.companies.manage') === true;
    }

    public function rules(): array
    {
        /** @var \App\Models\Company|null $company */
        $company = $this->route('company');
        $companyId = $company?->id;

        return [
            'name'    => ['sometimes', 'array'],
            'name.es' => ['sometimes', 'required', 'string', 'max:255'],
            'name.en' => ['sometimes', 'required', 'string', 'max:255'],

            'short_code' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:5',
                'regex:/^[A-Za-z]+$/',
                Rule::unique('companies', 'short_code')->ignore($companyId),
            ],

            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],

            'description'    => ['sometimes', 'array'],
            'description.es' => ['sometimes', 'nullable', 'string'],
            'description.en' => ['sometimes', 'nullable', 'string'],

            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    Company::STATUS_ACTIVE,
                    Company::STATUS_INACTIVE,
                    Company::STATUS_ARCHIVED,
                ]),
            ],

            'users'   => ['sometimes', 'array'],
            'users.*' => ['integer', 'exists:users,id'],

            'commission_beneficiary_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],

            'branding_text_dark'  => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding_bg_light'   => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding_text_light' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding_bg_dark'    => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            'branding_logo'        => ['sometimes', 'nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'branding_logo_remove' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name.es'     => 'nombre (ES)',
            'name.en'     => 'nombre (EN)',
            'short_code'  => 'código único',
            'phone'       => 'teléfono',
            'email'       => 'email',
            'description' => 'descripción',
        ];
    }
}
