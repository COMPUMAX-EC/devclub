<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessUnitCommissionUser extends Model
{
	use HasFactory;

	protected $table = 'business_unit_commission_users';

	protected $fillable = [
		'business_unit_id',
		'user_id',
		'commission',
	];

	protected $casts = [
		'business_unit_id' => 'integer',
		'user_id'          => 'integer',
		'commission'       => 'decimal:2',
	];

	public function businessUnit()
	{
		return $this->belongsTo(BusinessUnit::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
