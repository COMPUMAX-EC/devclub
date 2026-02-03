<?php

namespace App\Models;

use App\Casts\TranslatableJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coverage extends Model
{
    use HasFactory;

    protected $table = 'coverages';

    protected $fillable = [
        'name',
        'description',
        'unit_id',
        'category_id',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'name'        => TranslatableJson::class,
        'description' => TranslatableJson::class,
        'sort_order'  => 'integer',
    ];

    public function unit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function category()
    {
        return $this->belongsTo(CoverageCategory::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }
}
