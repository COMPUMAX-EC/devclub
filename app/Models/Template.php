<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Template extends Model
{
	use SoftDeletes;

	public const TYPE_HTML = 'HTML';
	public const TYPE_PDF  = 'PDF';

	protected $fillable = [
		'name',
		'slug',
		'type',
		'test_data_json',
		'active_template_version_id',
	];

	public function versions()
	{
		return $this->hasMany(TemplateVersion::class, 'template_id');
	}

	public function activeVersion()
	{
		return $this->belongsTo(TemplateVersion::class, 'active_template_version_id');
	}

	public function isTypePdf(): bool
	{
		return strtoupper((string) $this->type) === self::TYPE_PDF;
	}
	
    public function activeTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class, 'active_template_version_id');
    }

    public static function searchTemplate($slug = null)
    {
        return static::query()->where('slug', $slug)->first();
    }
}
