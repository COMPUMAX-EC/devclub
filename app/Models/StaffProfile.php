<?php

namespace App\Models;

class StaffProfile extends Model
{

	protected $primaryKey = 'user_id';
	public $incrementing  = false;
	protected $keyType	  = 'int';
	protected $fillable = [
	'work_phone',
	'commission_regular_first_year_pct',
	'commission_regular_renewal_pct',
	'commission_capitados_pct',
	'notes_admin',
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'user_id');
	}
}
