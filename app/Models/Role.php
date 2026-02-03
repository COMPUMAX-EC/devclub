<?php

namespace App\Models;

use App\Casts\TranslatableJson;
use App\Models\Concerns\HasTranslatableJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
	use HasFactory;
	use HasTranslatableJson;

	public const SCOPE_SYSTEM = 'system';
	public const SCOPE_UNIT	 = 'unit';

	protected $table = 'roles';

	protected $fillable = [
		'name',			// identificador corto del rol (spatie)
		'guard_name',	// típico "admin" / "web" / etc.
		'scope',		// system | unit
		'level',		// unsigned int (sin fallbacks)
		'label',		// traducible (es/en/...)
	];

	protected $casts = [
		'label' => TranslatableJson::class,
		'level' => 'integer',
	];

	/**
	 * Atributos agregados a la serialización JSON.
	 */
	protected $appends = [
		'role_name',
	];

	public function isScope(string $scope): bool
	{
		return $this->scope === $scope;
	}

	public function scopeSystem($query)
	{
		return $query->where('scope', self::SCOPE_SYSTEM);
	}

	public function scopeUnit($query)
	{
		return $query->where('scope', self::SCOPE_UNIT);
	}

	/**
	 * Accesor para el atributo calculado "role_name" (se incluye en JSON).
	 *
	 * - Si label tiene contenido, lo usa tal cual.
	 * - Si label está vacío, construye un nombre a partir de "name":
	 *   * "." => " - "
	 *   * "_" => " "
	 *   * luego lo convierte a Capitalized Case.
	 */
	public function getRoleNameAttribute(): string
	{
		$label = trim((string) ($this->label ?? ''));

		if ($label !== '') {
			return $label;
		}

		$name = (string) ($this->name ?? '');
		if ($name === '') {
			return '';
		}

		// Reemplazos: puntos por " - " y guiones bajos por espacios
		$normalized = str_replace(['.', '_'], [' - ', ' '], $name);

		// Normalizar espacios
		$normalized = preg_replace('/\s+/', ' ', $normalized ?? '');
		$normalized = trim($normalized);

		if ($normalized === '') {
			return '';
		}

		// Capitalized Case
		return Str::title(Str::lower($normalized));
	}

	/**
	 * Método de ayuda explícito para obtener el nombre de rol "bonito".
	 */
	public function roleName(): string
	{
		return $this->getRoleNameAttribute();
	}
}
