<?php

namespace App\Models\Concerns;

use App\Casts\TranslatableJson;
use Illuminate\Support\Arr;

trait HasTranslatableJson
{

	/**
	 * Los campos traducibles se determinan automáticamente
	 * a partir de los casts del modelo:
	 *
	 * protected $casts = [
	 *     'nombre' => TranslatableJson::class,
	 *     'descripcion' => TranslatableJson::class,
	 * ];
	 */
	public static function bootHasTranslatableJson(): void
	{
		// Se deja definido por si en el futuro se requiere lógica de boot.
		// Actualmente no es necesario hacer nada aquí porque los campos
		// traducibles se detectan dinámicamente a partir de los casts.
	}

	/**
	 * Devuelve el listado de atributos que usan el cast TranslatableJson.
	 */
	protected function getTranslatableKeys(): array
	{
		// Preferimos getCasts() por si el modelo la sobreescribe
		if (method_exists($this, 'getCasts'))
		{
			$casts = $this->getCasts();
		}
		else
		{
			$casts = (array) ($this->casts ?? []);
		}

		$keys		 = [];
		$targetClass = TranslatableJson::class;

		foreach ($casts as $attribute => $cast)
		{
			// En Laravel, para casts personalizados normalmente es un FQCN.
			// Aun así, soportamos el caso "Clase:algo" por seguridad.
			if (is_string($cast))
			{
				$castClass = explode(':', $cast, 2)[0];
			}
			else
			{
				$castClass = $cast;
			}

			if ($castClass === $targetClass)
			{
				$keys[] = $attribute;
			}
		}

		return $keys;
	}

	/**
	 * Devuelve string resuelto desde array por locale + fallback.
	 */
	protected function resolveTranslationValue($value): string
	{
		if (!is_array($value))
		{
			return (string) ($value ?? '');
		}

		$loc	  = app()->getLocale();
		$fallback = config('app.fallback_locale');

		$val = $value[$loc] ?? $value[$fallback] ?? null;

		if ($val === null)
		{
			foreach (config('app.supported_locales', []) as $l)
			{
				if (!empty($value[$l]))
				{
					$val = $value[$l];
					break;
				}
			}
		}

		return (string) ($val ?? '');
	}

	/**
	 * OVERRIDE: al leer atributos traducibles, devolver string traducido.
	 */
	public function getAttributeValue($key)
	{
		$val = parent::getAttributeValue($key);

		if (in_array($key, $this->getTranslatableKeys(), true))
		{
			return $this->resolveTranslationValue($val);
		}

		return $val;
	}

	/**
	 * Obtén el diccionario completo (para formularios) de un atributo traducible.
	 */
	public function getTranslations(string $attribute): array
	{
		// Intentar primero desde el valor original crudo (sin mutadores)
		$raw = $this->getRawOriginal($attribute);
		$arr = is_string($raw) ? json_decode($raw, true) : null;

		if (is_array($arr))
		{
			return $arr;
		}

		// Como respaldo, usa attributes sin resolve si existiera
		$attrVal = Arr::get($this->attributes ?? [], $attribute);
		$arr	 = is_string($attrVal) ? json_decode($attrVal, true) : $attrVal;

		return is_array($arr) ? $arr : [];
	}

	/**
	 * Lee traducción específica.
	 */
	public function getTranslation(string $attribute, ?string $locale = null, bool $fallback = true): ?string
	{
		$all	= $this->getTranslations($attribute);
		$locale = $locale ?: app()->getLocale();
		$val	= $all[$locale] ?? null;

		if (($val === null || $val === '') && $fallback)
		{
			$fb = $all[config('app.fallback_locale')] ?? null;

			if ($fb !== null && $fb !== '')
			{
				return $fb;
			}

			foreach (config('app.supported_locales', []) as $l)
			{
				if (!empty($all[$l]))
				{
					return $all[$l];
				}
			}
		}

		return $val;
	}

	/**
	 * Set de un locale específico.
	 */
	public function setTranslation(string $attribute, string $locale, ?string $value): static
	{
		$all		  = $this->getTranslations($attribute);
		$all[$locale] = ($value === '' ? null : $value);
		$this->setAttribute($attribute, $all);

		return $this;
	}

	/**
	 * Asigna todas las traducciones a la vez (ej: desde el form).
	 */
	public function fillTranslations(string $attribute, array $byLocale): static
	{
		$this->setAttribute($attribute, $byLocale);

		return $this;
	}
}
