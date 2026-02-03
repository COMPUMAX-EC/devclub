<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool { return $this->user('admin')->can('update', $this->route('user')); }

    public function rules(): array
    {
        $id = $this->route('user')->id ?? null;
        return [
            'first_name'  => ['required','string','max:100'],
            'last_name'   => ['required','string','max:120'],
            'display_name'=> ['nullable','string','max:120'],
            'email'       => ['required','email','max:190',"unique:users,email,{$id},id,realm,admin"],
            'status'      => ['required','in:active,suspended,locked'],
            'timezone'    => ['required','string','max:50'],
            'locale'      => ['required','in:es,en'],
            // Comisiones
            'commission_regular_first_year_pct' => ['nullable','numeric','min:0','max:100'],
            'commission_regular_renewal_pct'    => ['nullable','numeric','min:0','max:100'],
            'commission_capitados_pct'          => ['nullable','numeric','min:0','max:100'],
        ];
    }
}
