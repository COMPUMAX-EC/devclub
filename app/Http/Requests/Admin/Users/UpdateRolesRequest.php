<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRolesRequest extends FormRequest
{
    public function authorize(): bool { return $this->user('admin')->can('manageRoles', $this->route('user')); }

    public function rules(): array
    {
        return [
            'roles'   => ['required','array'],
            'roles.*' => ['string'],
        ];
    }
}
