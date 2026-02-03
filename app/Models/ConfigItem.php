<?php

namespace App\Models;

use App\Casts\TranslatableJson;
use App\Models\Concerns\HasDirectory;

class ConfigItem extends Model
{
    use HasDirectory;

    protected $table = 'config_items';

    protected $fillable = [
        'category',
        'token',
        'name',
        'type',
        'config',
        'value_int',
        'value_decimal',
        'value_text',
        'value_trans',
        'value_date',
        'value_file_plain_id',
        'value_file_es_id',
        'value_file_en_id',
    ];

    protected $casts = [
        'config'        => 'array',        // JSON en TEXT
        'value_int'     => 'int',
        'value_decimal' => 'float',
        'value_date'    => 'date',
        'value_trans'   => TranslatableJson::class, // <- importante
    ];

    // -----------------------------
    // Relaciones con File
    // -----------------------------

    public function filePlain()
    {
        return $this->belongsTo(File::class, 'value_file_plain_id');
    }

    public function fileEs()
    {
        return $this->belongsTo(File::class, 'value_file_es_id');
    }

    public function fileEn()
    {
        return $this->belongsTo(File::class, 'value_file_en_id');
    }

    // -----------------------------
    // Helpers de tipos
    // -----------------------------

    public function isTranslatedType(): bool
    {
        return in_array($this->type, [
            'input_text_translated',
            'textarea_translated',
            'html_translated',
        ], true);
    }

    public function isPlainTextType(): bool
    {
        return in_array($this->type, [
            'input_text_plain',
            'textarea_plain',
            'html_plain',
            'email',
            'url',
            'phone',
            'color',
            'json',
            'model_reference',
            'enum',
        ], true);
    }

    public function isHtmlType(): bool
    {
        return in_array($this->type, [
            'html_plain',
            'html_translated',
        ], true);
    }

    public function isFilePlainType(): bool
    {
        return $this->type === 'file_plain';
    }

    public function isFileTranslatedType(): bool
    {
        return $this->type === 'file_translated';
    }

    public function isDateType(): bool
    {
        return $this->type === 'date';
    }

    public function isBooleanType(): bool
    {
        return $this->type === 'boolean';
    }

    public function isIntegerType(): bool
    {
        return $this->type === 'integer';
    }

    public function isDecimalType(): bool
    {
        return $this->type === 'decimal';
    }

    // -----------------------------
    // Config (reglas) helpers
    // -----------------------------

    public function configArray(): array
    {
        return $this->config ?? [];
    }

    public function enumOptions(): array
    {
        $config = $this->configArray();
        return $config['options'] ?? [];
    }

    public function enumLabel(): ?string
    {
        if ($this->type !== 'enum') {
            return null;
        }

        $value = $this->value_text;
        if ($value === null || $value === '') {
            return null;
        }

        foreach ($this->enumOptions() as $opt) {
            if (($opt['key'] ?? null) === $value) {
                return $opt['label'] ?? $value;
            }
        }

        return $value;
    }

    /**
     * Restricciones para archivos sacadas de config.
     *
     * - extensions: array de extensiones en minúsculas, sin punto.
     * - max_size_kb: int|null.
     */
    public function fileConstraints(): array
    {
        $cfg = $this->configArray();

        $extsRaw    = $cfg['file_allowed_extensions'] ?? null;
        $extensions = [];

        if (is_string($extsRaw)) {
            $extensions = array_filter(array_map(
                static fn ($ext) => strtolower(ltrim(trim($ext), '.')),
                explode(',', $extsRaw)
            ));
        } elseif (is_array($extsRaw)) {
            $extensions = array_filter(array_map(
                static fn ($ext) => strtolower(ltrim((string) $ext, '.')),
                $extsRaw
            ));
        }

        $maxSizeKb = $cfg['file_max_size_kb'] ?? null;

        if (is_string($maxSizeKb) || is_int($maxSizeKb) || is_float($maxSizeKb)) {
            $maxSizeKb = (int) $maxSizeKb;
            if ($maxSizeKb <= 0) {
                $maxSizeKb = null;
            }
        } else {
            $maxSizeKb = null;
        }

        return [
            'extensions'  => $extensions,
            'max_size_kb' => $maxSizeKb,
        ];
    }

    // -----------------------------
    // Valor centralizado
    // -----------------------------

    /**
     * Devuelve el valor efectivo según el tipo.
     *
     * Para archivo, devuelve una URL pública (si existe), o null.
     */
    public function value(?string $locale = null)
    {
        $locale = $locale ?: app()->getLocale();

        switch ($this->type) {
            case 'integer':
                return $this->value_int;

            case 'boolean':
                return $this->value_int === null ? null : (bool) $this->value_int;

            case 'decimal':
                return $this->value_decimal;

            case 'date':
                return $this->value_date;

            // Texto / HTML sin traducción
            case 'input_text_plain':
            case 'textarea_plain':
            case 'html_plain':
            case 'email':
            case 'url':
            case 'phone':
            case 'color':
            case 'json':
            case 'model_reference':
                return $this->value_text;

            case 'enum':
                return $this->value_text; // clave; para mostrar legible usar enumLabel()

            // Texto traducible
            case 'input_text_translated':
            case 'textarea_translated':
            case 'html_translated':
                return $this->value_trans; // cast devuelve string en locale actual

            // Archivos
            case 'file_plain':
            case 'file_translated':
                return $this->fileForLocale($locale);

            default:
                return null;
        }
    }

    // -----------------------------
    // Resolución de archivos
    // -----------------------------

    public function fileForLocale(?string $locale = null): ?File
    {
        if ($this->isFilePlainType()) {
            return $this->filePlain;
        }

        if (! $this->isFileTranslatedType()) {
            return null;
        }

        $locale = $locale ?: app()->getLocale();

        if ($locale === 'es') {
            return $this->fileEs ?: $this->fileEn;
        }

        if ($locale === 'en') {
            return $this->fileEn ?: $this->fileEs;
        }

        // Fallback: ES, luego EN
        return $this->fileEs ?: $this->fileEn;
    }

    public function publicFileUrl(?string $locale = null): ?string
    {
        $file = $this->fileForLocale($locale);
        return $file?->url();
    }

    public function temporaryPublicFileUrl(
        ?string $locale = null,
        ?int $minutes = null
    ): ?string {
        $file = $this->fileForLocale($locale);
        return $file?->temporaryUrl($minutes);
    }

    // -----------------------------
    // Helpers estáticos de acceso
    // -----------------------------

    /**
     * Helper estático para obtener ConfigItem(s) por categoría y token.
     *
     * - Si se pasa $token:
     *     ConfigItem::searchBy('categoria', 'token') => mixed (value())
     * - Si $token es null:
     *     ConfigItem::searchBy('categoria') => array asociativo [token => value()]
     */
    private static function searchBy(string $category, ?string $token = null)
    {
        $query = static::query()->where('category', $category);

        if ($token !== null) {
            // Devuelve el valor efectivo (value()) del item encontrado
            return $query->where('token', $token)->first()->value();
        }

        // Devuelve array [token => value()] usando el valor efectivo de cada item
        return $query
            ->get()
            ->mapWithKeys(function (self $item) {
                return [$item->token => $item->value()];
            })
            ->all();
    }
	/**
	 * 
	 * @param string $category
	 * @return ConfigItem[]
	 */
    public static function searchByCategory(string $category)
    {
		return self::searchBy($category);
    }
	/**
	 * 
	 * @param string $category
	 * @param type $token
	 * @return ConfigItem
	 */
    public static function searchByToken(string $category, $token)
    {
		return self::searchBy($category, $token);
    }
}
