<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class TranslatableJson implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): array
    {
        if ($value === null || $value === '') return [];
        $arr = json_decode($value, true);
        return is_array($arr) ? $arr : [];
    }

    public function set($model, string $key, $value, array $attributes): array
    {
        // Permite asignar string: lo pone en el locale actual
        if (is_string($value)) {
            $value = [app()->getLocale() => $value];
        }

        $locales = config('app.supported_locales', [config('app.locale')]);
        $clean   = [];
        if (is_array($value)) {
            foreach ($locales as $loc) {
                if (array_key_exists($loc, $value)) {
                    $v = $value[$loc];
                    $clean[$loc] = ($v === '' ? null : $v);
                }
            }
        }

        return [$key => json_encode($clean, JSON_UNESCAPED_UNICODE)];
    }
}
