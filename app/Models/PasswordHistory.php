<?php

namespace App\Models;

class PasswordHistory extends Model
{

	public $timestamps	= false;
	protected $fillable = ['user_id', 'password_hash', 'created_at'];
}
