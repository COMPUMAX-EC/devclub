<?php

namespace App\Models;

use App\Models\User;
use App\Models\File;
use App\Models\ConfigItem;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'name',
        'short_code',
        'phone',
        'email',
        'description',
        'status',
        'commission_beneficiary_user_id',
        'branding_text_dark',
        'branding_bg_light',
        'branding_text_light',
        'branding_bg_dark',
        'branding_logo_file_id',
        'pdf_template_id',
    ];

    protected $casts = [
        'commission_beneficiary_user_id' => 'integer',
        'branding_logo_file_id'          => 'integer',
        'pdf_template_id'                => 'integer',
    ];

    // ---------------------------------
    // Relaciones
    // ---------------------------------

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function commissionBeneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commission_beneficiary_user_id');
    }

    public function brandingLogo(): BelongsTo
    {
        return $this->belongsTo(File::class, 'branding_logo_file_id');
    }

    public function pdfTemplate(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'pdf_template_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function capitatedProducts(): HasMany
    {
        return $this->products()->ofType(Product::TYPE_PLAN_CAPITADO);
    }

    // ---------------------------------
    // Branding defaults
    // ---------------------------------

    /**
     * Defaults globales de branding (colores + logo).
     *
     * Lee los valores desde config('company_branding.defaults') que a su vez
     * apuntan a registros ConfigItem (category + token).
     *
     * Los colores devueltos NO incluyen el prefijo "#", para que la vista
     * pueda mostrar "Ej: FFFFFF" y renderizar el "#" en el <span> separado.
     */
    public static function brandingDefaults(): array
    {
        // Mapa de configuración: text_dark, bg_light, text_light, bg_dark, logo
        $configDefaults = config('company_branding', []);

        // Fallbacks en caso de no existir ConfigItem o estar vacío / inválido.
        // Siempre sin "#".
        $fallbackColors = [
            'text_dark'  => '000000',
            'bg_light'   => 'FFFFFF',
            'text_light' => 'FFFFFF',
            'bg_dark'    => '000000',
        ];

        $colors = [];
        foreach ($fallbackColors as $key => $fallback) {
            $colors[$key] = static::resolveColorDefault($configDefaults, $key, $fallback);
		}

        // Logo por defecto (puede ser null)
        $logo       = static::resolveLogoDefault($configDefaults, 'logo');

        return [
            'text_dark'  => $colors['text_dark'],
            'bg_light'   => $colors['bg_light'],
            'text_light' => $colors['text_light'],
            'bg_dark'    => $colors['bg_dark'],
            'logo'       => $logo,
        ];
    }

    /**
     * Resuelve un color por clave ('text_dark', 'bg_light', etc.) a partir
     * de la configuración de company_branding.php y ConfigItem.
     *
     * Devuelve SIEMPRE un string de 3 ó 6 dígitos hexadecimales SIN "#".
     */
    protected static function resolveColorDefault(array $configDefaults, string $key, string $fallback): string
    {
		$category = $configDefaults['category'];
		$token    = $configDefaults[$key];

        /** @var ConfigItem|null $item */
        $item = ConfigItem::query()
            ->where('category', $category)
            ->where('token', $token)
            ->first();

        if (!$item) {
            return strtoupper($fallback);
        }

        // Para tipo "color" el método value() devuelve value_text.
        $value = $item->value();

        if ($value === null || $value === '') {
            return strtoupper($fallback);
        }

        $color = ltrim(trim((string) $value), '#');

        if (!preg_match('/^[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color)) {
            return strtoupper($fallback);
        }

        return strtoupper($color);
    }

    /**
     * Resuelve el logo por defecto a partir de company_branding.php.
     *
     * Devuelve:
     *   [
     *     'id'            => int,
     *     'url'           => string,
     *     'original_name' => string,
     *   ]
     * o null si no hay logo válido.
     */
    protected static function resolveLogoDefault(?array $configDefaults, $key): ?array
    {
		$category = $configDefaults['category'];
		$token    = $configDefaults[$key];

        /** @var ConfigItem|null $item */
        $item = ConfigItem::query()
            ->where('category', $category)
            ->where('token', $token)
            ->first();

        if (!$item) {
            return null;
        }

        // Usamos la lógica de ConfigItem para archivos (plain o traducidos).
        $file = $item->fileForLocale();

        if (!$file instanceof File) {
            return null;
        }

        return [
            'id'            => $file->id,
            'url'           => $file->url(),
            'original_name' => $file->original_name,
        ];
    }

    /**
     * Devuelve el branding efectivo de la empresa:
     * - Colores propios si están definidos en la empresa.
     * - En blanco => usa defaults globales resueltos desde ConfigItem.
     *
     * Los colores en el array final SIEMPRE llevan "#" al inicio.
     */
    public function brandingConfig(): array
    {
        $defaults = static::brandingDefaults();

        // Valores crudos en BD (sin "#"), con fallback a defaults globales.
        $rawTextDark  = $this->branding_text_dark ?: $defaults['text_dark'];
		$rawBgLight	  = $this->branding_bg_light ?: $defaults['bg_light'];
		$rawTextLight = $this->branding_text_light ?: $defaults['text_light'];
		$rawBgDark	  = $this->branding_bg_dark ?: $defaults['bg_dark'];

		$colors = [
            'text_dark'  => '#' . ltrim($rawTextDark, '#'),
            'bg_light'   => '#' . ltrim($rawBgLight, '#'),
            'text_light' => '#' . ltrim($rawTextLight, '#'),
            'bg_dark'    => '#' . ltrim($rawBgDark, '#'),
        ];

        // Logo efectivo: primero el de la empresa, si no el default global.
        $logoFile = $this->brandingLogo;
        if ($logoFile) {
            $logo = [
                'id'            => $logoFile->id,
                'url'           => $logoFile->url(),
                'original_name' => $logoFile->original_name,
                'is_custom'     => true,
            ];
        } else {
            $logo = $defaults['logo'] ?? null;

            if (is_array($logo)) {
                $logo['is_custom'] = false;
            }
        }

        return [
            'text_dark'  => $colors['text_dark'],
            'bg_light'   => $colors['bg_light'],
            'text_light' => $colors['text_light'],
            'bg_dark'    => $colors['bg_dark'],

            'custom_text_dark'  => $this->branding_text_dark,
            'custom_bg_light'   => $this->branding_bg_light,
            'custom_text_light' => $this->branding_text_light,
            'custom_bg_dark'    => $this->branding_bg_dark,

            'logo' => $logo,
        ];
    }
	
    /**
     * Defaults globales de branding (colores + logo).
     *
     * Lee los valores desde config('company_branding.defaults') que a su vez
     * apuntan a registros ConfigItem (category + token).
     *
     * Los colores devueltos NO incluyen el prefijo "#", para que la vista
     * pueda mostrar "Ej: FFFFFF" y renderizar el "#" en el <span> separado.
     */
    public function branding(): array
    {
		$branding = self::defaultBranding();

		// cargo los defaults de la compania
		$map = [
			'text_dark'	 => 'branding_text_dark',
			'text_light' => 'branding_text_light',
			'bg_light'	 => 'branding_bg_light',
			'bg_dark'	 => 'branding_bg_dark',
			'logo'		 => 'brandingLogo',
		];
		
		foreach ($map as $destiny=>$origin)
		{
			$val = $this->{$origin};
			if(!empty($val))
			{
				if($val instanceof \App\Models\File)
				{

				}
				else
				{
					$val = '#'.$val;
				}
				
				$branding[$destiny] = $val;
			}
		}

		return $branding;
    }
	
	public static function defaultBranding()
	{
		$values = \App\Models\ConfigItem::searchByCategory(config('company_branding.category'));
		return [
			'logo'		 => $values[config('company_branding.logo')],
			'text_light' => '#' . $values[config('company_branding.text_light')],
			'text_dark'	 => '#' . $values[config('company_branding.text_dark')],
			'bg_light'	 => '#' . $values[config('company_branding.bg_light')],
			'bg_dark'	 => '#' . $values[config('company_branding.bg_dark')],
		];
	}
}
