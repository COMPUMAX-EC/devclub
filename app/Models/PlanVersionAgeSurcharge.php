<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanVersionAgeSurcharge extends Model
{
	protected $table = 'plan_version_age_surcharges';

	protected $fillable = [
		'plan_version_id',
		'age_from',
		'age_to',
		'surcharge_percent',
	];

	public function planVersion()
	{
		return $this->belongsTo(PlanVersion::class);
	}
}
