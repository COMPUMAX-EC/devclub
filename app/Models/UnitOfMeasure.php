<?php

namespace App\Models;

use App\Casts\TranslatableJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitOfMeasure extends Model
{

	use HasFactory;

	protected $table = 'units_of_measure';
	protected $fillable = [
		'name',
		'description',
		'measure_type',
		'status',
	];

	protected $casts = [
		'name'		  => TranslatableJson::class,
		'description' => TranslatableJson::class,
	];

	public const TYPE_INTEGER = 'integer';
	public const TYPE_DECIMAL = 'decimal';
	public const TYPE_TEXT	  = 'text';
	public const TYPE_NONE	  = 'none';

	public static function measureTypes(): array
	{
		return [
			self::TYPE_INTEGER,
			self::TYPE_DECIMAL,
			self::TYPE_TEXT,
			self::TYPE_NONE,
		];
	}

	public function scopeActive($query)
	{
		return $query->where('status', 'active');
	}
}
