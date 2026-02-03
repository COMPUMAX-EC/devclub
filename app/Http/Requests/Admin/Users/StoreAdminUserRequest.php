<?php

namespace App\Http\Requests\Admin\Users;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminUserRequest extends FormRequest
{
    public function authorize(): bool { return $this->user('admin')->can('create', \App\Models\User::class); }

    public function rules(): array
    {
        return [
            'first_name'  => ['required','string','max:100'],
            'last_name'   => ['required','string','max:120'],
            'display_name'=> ['nullable','string','max:120'],
            'email'       => ['required','email','max:190','unique:users,email,NULL,id,realm,admin'],
            'status'      => ['required','in:active,suspended,locked'],
            'timezone'    => ['required','string','max:50'],
            'locale'      => ['required','in:es,en'],
            'roles'       => ['array'],
            'roles.*'     => ['string'], // validación fina en controlador (por mezclas prohibidas)
            // Comisiones (opcionales, obligatorias si rol vendedor*)
            'commission_regular_first_year_pct' => ['nullable','numeric','min:0','max:100'],
            'commission_regular_renewal_pct'    => ['nullable','numeric','min:0','max:100'],
            'commission_capitados_pct'          => ['nullable','numeric','min:0','max:100'],
        ];
    }
}
