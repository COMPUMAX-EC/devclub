<?php

namespace App\Models;

use App\Casts\TranslatableJson;
use App\Models\Country;
use App\Models\File;
use App\Models\PlanVersionCoverage;
use App\Models\Product;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanVersion extends Model
{

	use HasFactory;

	protected $table = 'plan_versions';

	public const STATUS_INACTIVE = 'inactive';
	public const STATUS_ACTIVE	 = 'active';
	public const STATUS_ARCHIVED = 'archived';

	/**
	 * Extensiones permitidas para los términos y condiciones.
	 */
	public const TERMS_FILE_MAX_SIZE_KB		   = 20480; // 20 MB
	public const TERMS_FILE_ALLOWED_EXTENSIONS = ['pdf',];

	/**
	 * Campos rellenables.
	 */
	protected $fillable = [
		'product_id',
		'name',
		'status',
		'max_entry_age',
		'max_renewal_age',
		'wtime_suicide',
		'wtime_preexisting_conditions',
		'wtime_accident',
		// Campos históricos: se mantienen en BD pero ya no se usan para activación
		'country_id',
		'zone_id',
		'price_1',
		'price_2',
		'price_3',
		'price_4',
		'terms_file_es_id',
		'terms_file_en_id',
		'status',
		'terms_html',
	];

	/**
	 * Casts nativos.
	 */
	protected $casts = [
		'product_id'				   => 'integer',
		'max_entry_age'				   => 'integer',
		'max_renewal_age'			   => 'integer',
		'wtime_suicide'				   => 'integer',
		'wtime_preexisting_conditions' => 'integer',
		'wtime_accident'			   => 'integer',
		'country_id'				   => 'integer',
		'zone_id'					   => 'integer',
		'price_1'					   => 'decimal:2',
		'price_2'					   => 'decimal:2',
		'price_3'					   => 'decimal:2',
		'price_4'					   => 'decimal:2',
		'terms_file_es_id'			   => 'integer',
		'terms_file_en_id'			   => 'integer',
		'terms_html'				   => TranslatableJson::class,
	];

	/**
	 * Atributos calculados que se añaden al JSON.
	 */
	protected $appends = [
		'can_be_activated',
		'is_in_use',
		'is_deletable',
		// Para el front (dos archivos)
		'terms_file_es_url',
		'terms_file_en_url',
		'terms_file_accept',
	];

	/*
	  |--------------------------------------------------------------------------
	  | Relaciones
	  |--------------------------------------------------------------------------
	 */

	public function product(): BelongsTo
	{
		return $this->belongsTo(Product::class);
	}

	public function coverages(): HasMany
	{
		return $this->hasMany(PlanVersionCoverage::class)
						->orderBy('sort_order')
						->orderBy('id');
	}

	public function country(): BelongsTo
	{
		return $this->belongsTo(Country::class);
	}

	public function zone(): BelongsTo
	{
		return $this->belongsTo(Zone::class);
	}

	/**
	 * Países asociados a esta versión vía pivote plan_version_countries.
	 * Incluye el precio específico por país en el pivote.
	 */
	public function countries(): BelongsToMany
	{
		return $this->belongsToMany(Country::class, 'plan_version_countries')
						->withPivot('price')
						->withTimestamps();
	}

	/**
	 * Países asociados a esta versión vía pivote plan_version_repatriation_countries.
	 * Marca los países permitidos para repatriación (sin tarifa).
	 */
	public function repatriationCountries(): BelongsToMany
	{
		return $this->belongsToMany(Country::class, 'plan_version_repatriation_countries')
						->withTimestamps();
	}

	public function termsFileEs(): BelongsTo
	{
		return $this->belongsTo(File::class, 'terms_file_es_id');
	}

	public function termsFileEn(): BelongsTo
	{
		return $this->belongsTo(File::class, 'terms_file_en_id');
	}

	/*
	  |--------------------------------------------------------------------------
	  | Scopes
	  |--------------------------------------------------------------------------
	 */

	public function scopeActive(Builder $query): Builder
	{
		return $query->where('status', self::STATUS_ACTIVE);
	}

	/*
	  |--------------------------------------------------------------------------
	  | Accessors simples
	  |--------------------------------------------------------------------------
	 */

	public function getStatusLabelAttribute(): string
	{
		return match ($this->status)
		{
			self::STATUS_INACTIVE => 'Inactiva',
			self::STATUS_ACTIVE	  => 'Activa',
			self::STATUS_ARCHIVED => 'Archivada',
			default				  => ucfirst((string) $this->status),
		};
	}

	public function getCanBeActivatedAttribute(): bool
	{
		return $this->canBeActivated();
	}

	public function getIsInUseAttribute(): bool
	{
		return $this->isInUse();
	}

	public function getIsDeletableAttribute(): bool
	{
		return !$this->isInUse();
	}

	public function getTermsFileEsUrlAttribute(): ?string
	{
		return $this->termsFileEs ? $this->termsFileEs->url() : null;
	}

	public function getTermsFileEnUrlAttribute(): ?string
	{
		return $this->termsFileEn ? $this->termsFileEn->url() : null;
	}

	/*
	  |--------------------------------------------------------------------------
	  | Lógica de negocio
	  |--------------------------------------------------------------------------
	  |
	  | Reglas mínimas para poder activar una versión:
	  |
	  | - Debe tener product_id.
	  | - max_entry_age y max_renewal_age:
	  |     * no nulos
	  |     * > 0
	  |     * max_entry_age <= max_renewal_age
	  | - wtime_suicide, wtime_preexisting_conditions, wtime_accident:
	  |     * no nulos
	  |     * >= 0
	  | - price_1:
	  |     * no nulo
	  |     * > 0
	  | - terms_file_es_id y terms_file_en_id no nulos.
	  |
	  | Ya NO depende de:
	  | - country_id
	  | - zone_id
	  | - price_2
	  |--------------------------------------------------------------------------
	 */

	public function canBeActivated(): bool
	{
		// Restricción temporalmente deshabilitada: permitimos activar siempre
		return true;
	}

	/**
	 * En el futuro aquí puedes implementar lógica real de "en uso".
	 */
	public function isInUse(): bool
	{
		// Por ahora no se utiliza en otras tablas.
		return false;
	}

	/*
	  |--------------------------------------------------------------------------
	  | Validación de archivos de términos y condiciones
	  |--------------------------------------------------------------------------
	 */

	public static function termsFileValidationRules(): array
	{
		$extensions = implode(',', self::TERMS_FILE_ALLOWED_EXTENSIONS);
		$maxSize	= self::TERMS_FILE_MAX_SIZE_KB;

		return [
			'file',
			"mimes:{$extensions}",
			"max:{$maxSize}",
		];
	}

	public function getTermsFileAcceptAttribute(): string
	{
		return self::TERMS_FILE_ALLOWED_EXTENSIONS ? '.' . implode(',.', self::TERMS_FILE_ALLOWED_EXTENSIONS) : '';
	}

	/**
	 * Devuelve la ruta local (en caché) del PDF de términos y condiciones
	 * según el idioma del usuario/app, con fallback al otro idioma.
	 *
	 * - Si $locale es null, se usa app()->getLocale().
	 * - Si el locale es "es-XX" → intenta ES y luego EN.
	 * - Si el locale es cualquier otro → intenta EN y luego ES.
	 *
	 * @param  string|null  $locale
	 * @return string|null  Ruta absoluta local al archivo o null si no hay ninguno disponible.
	 */
	public function getLocalizedTermsLocalPath(?string $locale = null): ?string
	{
		// Locale preferido (normalmente ya configurado según el usuario logueado)
		$locale = $locale ?: app()->getLocale();
		$locale = strtolower((string) $locale);

		// Normalizamos a es/en
		$primaryLang  = str_starts_with($locale, 'es') ? 'es' : 'en';
		$fallbackLang = $primaryLang === 'es' ? 'en' : 'es';

		foreach ([$primaryLang, $fallbackLang] as $lang)
		{
			$relation = $lang === 'es' ? 'termsFileEs' : 'termsFileEn';

			/** @var File|null $file */
			$file = $this->{$relation};

			if (!$file)
			{
				continue;
			}

			try
			{
				$path = $file->localPath();

				// Por seguridad, verificamos que realmente exista el archivo local.
				if (is_string($path) && $path !== '' && file_exists($path))
				{
					return $path;
				}
			}
			catch (\Throwable $e)
			{
				// Si falla (no se puede leer/descargar), probamos con el otro idioma.
				continue;
			}
		}

		// No se encontró ningún archivo válido en ningún idioma
		return null;
	}
	
	public function ageSurcharges()
	{
		return $this
			->hasMany(PlanVersionAgeSurcharge::class)
			->orderBy('age_from');
	}
}
