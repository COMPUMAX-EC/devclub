<?php

namespace App\Models;

use App\Casts\TranslatableJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CoverageCategory extends Model
{

	use HasFactory;

	protected $table = 'coverage_categories';
	protected $fillable = [
		'name',
		'description',
		'status',
		'sort_order',
	];

	protected $casts = [
		'name'		  => TranslatableJson::class,
		'description' => TranslatableJson::class,
		'sort_order'  => 'integer',
	];

	// Relaciones
	public function coverages()
	{
		return $this->hasMany(Coverage::class, 'category_id')
						->orderBy('sort_order');
	}

	// Scopes
	public function scopeActive($query)
	{
		return $query->where('status', 'active');
	}

	public function scopeArchived($query)
	{
		return $query->where('status', 'archived');
	}
}
