<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateVersion extends Model
{
	protected $fillable = [
		'template_id',
		'name',
		'content',
		'test_data_json',
	];

	public function template()
	{
		return $this->belongsTo(Template::class, 'template_id');
	}
}
