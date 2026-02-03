<?php

namespace App\Models;


class AuditLog extends Model
{

	public $timestamps	= false; // usamos created_at manual
	protected $fillable = [
		'actor_user_id', 'target_user_id', 'realm', 'action', 'context_json', 'ip', 'user_agent', 'created_at'
	];
	protected $casts	= ['context_json' => 'array', 'created_at' => 'datetime'];
}
