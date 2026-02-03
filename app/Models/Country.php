<?php

namespace App\Models;

use App\Casts\TranslatableJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'iso2',
        'iso3',
        'continent_code',
        'phone_code',
        'is_active',
    ];

    /**
     * Casts.
     *
     * IMPORTANTE: aquí se declara explícitamente el cast de 'name'
     * para que, cuando se llame a setAttribute('name', $array),
     * se aplique el cast TranslatableJson inmediatamente.
     */
    protected $casts = [
        'name'      => TranslatableJson::class,
        'is_active' => 'boolean',
    ];

    public function zones()
    {
        return $this->belongsToMany(Zone::class)
            ->withTimestamps();
    }
	
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function findByIso2(?string $iso2, bool $onlyActive = true): ?self
    {
        $iso2 = $iso2 ? strtoupper(trim($iso2)) : null;

        if (!$iso2) {
            return null;
        }

        $query = static::query()->where('iso2', $iso2);

        if ($onlyActive) {
            $query->active();
        }

        return $query->first();
    }

	/**
	 * Permite obtener raw_name como propiedad
	 * @return type
	 */
    public function getRawNameAttribute()
    {
        // Devuelve el valor crudo de la columna 'name'
        return $this->getTranslations('name');
    }
}
