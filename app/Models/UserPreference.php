<?php

namespace App\Models;

class UserPreference extends Model
{

	protected $fillable = ['user_id', 'key', 'value_json'];
	protected $casts	= ['value_json' => 'array'];
}
