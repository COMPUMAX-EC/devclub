<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $continentKeys = array_keys(config('continents.options', config('continents', [])));

        $country = $this->route('country'); // id o modelo

        return [
            'name'        => ['required', 'array'],
            'name.es'     => ['required', 'string', 'max:255'],
            'name.en'     => ['required', 'string', 'max:255'],

            'iso2'        => [
                'required',
                'string',
                'size:2',
                'alpha',
                Rule::unique('countries', 'iso2')->ignore($country),
            ],
            'iso3'        => [
                'required',
                'string',
                'size:3',
                'alpha',
                Rule::unique('countries', 'iso3')->ignore($country),
            ],

            'continent_code' => [
                'required',
                'string',
                Rule::in($continentKeys),
            ],

            // OPCIONAL
            'phone_code'  => ['nullable', 'string', 'regex:/^[0-9]+$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name.es'        => 'nombre en español',
            'name.en'        => 'nombre en inglés',
            'iso2'           => 'código ISO de 2 letras',
            'iso3'           => 'código ISO de 3 letras',
            'continent_code' => 'continente',
            'phone_code'     => 'código telefónico',
        ];
    }
}
