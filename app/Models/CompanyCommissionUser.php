<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyCommissionUser extends Model
{
	use HasFactory;

	protected $table = 'company_commission_users';

	protected $fillable = [
		'company_id',
		'user_id',
		'commission',
	];

	protected $casts = [
		'company_id' => 'integer',
		'user_id'    => 'integer',
		'commission' => 'decimal:2',
	];

	public function company()
	{
		return $this->belongsTo(Company::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
}
