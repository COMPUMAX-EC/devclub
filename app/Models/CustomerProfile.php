<?php

namespace App\Models;

class CustomerProfile extends Model
{

	protected $primaryKey = 'user_id';
	public $incrementing  = false;
	protected $keyType	  = 'int';
	protected $fillable = [
		'mobile_e164', 'alt_email', 'doc_type', 'doc_number', 'birth_date', 'gender',
		'home_address_json', 'preferred_language', 'contact_via',
		'emergency_name', 'emergency_phone_e164', 'emergency_relation',
		'billing_name', 'tax_id', 'billing_address_json', 'tags', 'notes_internal'
	];
	protected $casts = [
		'home_address_json'	   => 'array',
		'billing_address_json' => 'array',
		'tags'				   => 'array',
		'birth_date'		   => 'date',
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'user_id');
	}
}
