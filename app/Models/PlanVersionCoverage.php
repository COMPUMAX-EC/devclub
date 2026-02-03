<?php

namespace App\Models;

use App\Casts\TranslatableJson;
use App\Models\UnitOfMeasure;
use App\Support\FormatService;

class PlanVersionCoverage extends Model
{

    protected $table = 'plan_version_coverages';

    protected $fillable = [
        'plan_version_id',
        'coverage_id',
        'sort_order',
        'value_int',
        'value_decimal',
        'value_text',  // JSON es/en
        'notes',       // JSON es/en (observaciones)
    ];

    protected $casts = [
        'value_int'     => 'integer',
        'value_decimal' => 'decimal:2',
        'value_text'    => TranslatableJson::class,
        'notes'         => TranslatableJson::class,
    ];

    public function planVersion()
    {
        return $this->belongsTo(PlanVersion::class);
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }

    public function unit()
    {
        // acceso rápido a la unidad de medida
        return $this->coverage ? $this->coverage->unit : null;
    }

    /**
     * Helper para obtener un valor unificado según el tipo de unidad.
     */
    public function getDisplayValueAttribute()
    {
		/* @var $formatter FormatService */
		$formatter = app(FormatService::class);
		
        $unit = $this->coverage->unit;
        if (!$unit) {
            return null;
        }

        switch ($unit->measure_type) {
            case UnitOfMeasure::TYPE_TEXT:
                // text: usamos el JSON traducible
                return $this->value_text;

            case UnitOfMeasure::TYPE_INTEGER:
                // entero
                return $formatter->integer($this->value_int).' '.$unit->name;

            case UnitOfMeasure::TYPE_DECIMAL:
                // decimal
                return $formatter->decimal($this->value_decimal).' '.$unit->name;

            case UnitOfMeasure::TYPE_NONE:
				return $unit->name;
            default:
                return null;
        }
    }
}