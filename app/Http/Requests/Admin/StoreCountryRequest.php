<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $continentKeys = array_keys(config('continents.options', config('continents', [])));

        return [
            'name'        => ['required', 'array'],
            'name.es'     => ['required', 'string', 'max:255'],
            'name.en'     => ['required', 'string', 'max:255'],

            'iso2'        => ['required', 'string', 'size:2', 'alpha', 'unique:countries,iso2'],
            'iso3'        => ['required', 'string', 'size:3', 'alpha', 'unique:countries,iso3'],

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
