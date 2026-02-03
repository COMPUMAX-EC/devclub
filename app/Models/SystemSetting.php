<?php

namespace App\Models;

class SystemSetting extends Model
{

	protected $fillable = ['category', 'key', 'value_json'];
	protected $casts	= ['value_json' => 'array'];

	public static function get(string $key, $default = null)
	{
		$row = static::query()->where('key', $key)->first();
		return $row?->value_json ?? $default;
	}
}
