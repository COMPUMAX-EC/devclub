<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool { return $this->user('admin')->can('changeStatus', $this->route('user')); }

    public function rules(): array
    {
        return [
            'status' => ['required','in:active,suspended,locked'],
        ];
    }
}
