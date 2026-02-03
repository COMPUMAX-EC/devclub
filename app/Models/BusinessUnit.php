<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class BusinessUnit extends Model
{
	use HasFactory;

	public const TYPE_CONSOLIDATOR = 'consolidator';
	public const TYPE_OFFICE	   = 'office';
	public const TYPE_COUNTER	   = 'counter';
	public const TYPE_FREELANCE	   = 'freelance';

	public const STATUS_ACTIVE   = 'active';
	public const STATUS_INACTIVE = 'inactive';

	protected $fillable = [
		'type',
		'name',
		'status',
		'parent_id',
		'created_by',

		// Branding
		'branding_json',
		'branding_text_dark',
		'branding_bg_light',
		'branding_text_light',
		'branding_bg_dark',
		'branding_logo_file_id',

		// Legacy (por compatibilidad si existiera en DB)
		'logo_file_id',
	];

	protected $casts = [
		'branding_json' => 'array',
	];

	public function parent(): BelongsTo
	{
		return $this->belongsTo(self::class, 'parent_id');
	}

	public function children(): HasMany
	{
		return $this->hasMany(self::class, 'parent_id');
	}

	public function memberships(): HasMany
	{
		return $this->hasMany(BusinessUnitMembership::class, 'business_unit_id');
	}

	/**
	 * Usuarios que reciben comisión GSA en esta unidad.
	 */
	public function gsaCommissions(): HasMany
	{
		return $this->hasMany(BusinessUnitCommissionUser::class, 'business_unit_id');
	}

	/**
	 * Logo de branding (el que usa el módulo).
	 */
	public function logoFile(): BelongsTo
	{
		return $this->belongsTo(File::class, 'branding_logo_file_id');
	}

	public function isActive(): bool
	{
		return $this->status === self::STATUS_ACTIVE;
	}

	public function isRoot(): bool
	{
		return $this->parent_id === null;
	}

	public function isFreelance(): bool
	{
		return $this->type === self::TYPE_FREELANCE;
	}

	public function isConsolidator(): bool
	{
		return $this->type === self::TYPE_CONSOLIDATOR;
	}

	public function isOffice(): bool
	{
		return $this->type === self::TYPE_OFFICE;
	}

	public function isCounter(): bool
	{
		return $this->type === self::TYPE_COUNTER;
	}

	/**
	 * Nombre "visible" de la unidad:
	 * - Si NO es freelance => usa name tal cual.
	 * - Si es freelance y tiene exactamente un membership con usuario => fullname del usuario.
	 * - En cualquier otro caso => fallback a name.
	 */
	public function displayName(): string
	{
		$baseName = trim((string) ($this->name ?? ''));

		if (!$this->isFreelance())
		{
			return $baseName;
		}

		// Aseguramos tener memberships + user cargados sin romper si ya están eager-loaded.
		$this->loadMissing('memberships.user');

		$memberships = $this->memberships
			? $this->memberships->filter(function ($membership) {
				return $membership && $membership->user;
			})
			: collect();

		// Regla de negocio: sólo si hay un único usuario inscrito.
		if ($memberships->count() !== 1)
		{
			return $baseName;
		}

		/** @var \App\Models\BusinessUnitMembership $membership */
		$membership = $memberships->first();
		$user       = $membership->user;

		if (!$user)
		{
			return $baseName;
		}

		// 1) fullname (lo que pediste)
		$fullName = trim((string) ($user->fullName ?? ''));

		// 2) Si fullname viene vacío, fallback a display_name
		if ($fullName === '')
		{
			$fullName = trim((string) ($user->display_name ?? ''));
		}

		// 3) Si aún está vacío, fallback a first_name + last_name
		if ($fullName === '')
		{
			$first = trim((string) ($user->first_name ?? ''));
			$last  = trim((string) ($user->last_name ?? ''));
			$fullName = trim($first . ' ' . $last);
		}

		// 4) Último fallback: nombre de la unidad
		return $fullName !== '' ? $fullName : $baseName;
	}

	// -------------------------------------------------
	// Helpers requeridos por el resolver / controladores
	// -------------------------------------------------

	/**
	 * Cadena de ancestros desde raíz -> ... -> $this (incluye a $this).
	 * Previene loops si hubiera un ciclo accidental.
	 */
	public function ancestorChain(): Collection
	{
		$chain = [];
		$seen = [];

		$node = $this;
		while ($node)
		{
			$id = (int) $node->getKey();
			if ($id > 0 && isset($seen[$id])) break;
			if ($id > 0) $seen[$id] = true;

			$chain[] = $node;

			$node->loadMissing('parent');
			$node = $node->parent;
		}

		return collect(array_reverse($chain));
	}

	public function isMember(User $user): bool
	{
		return $this->memberships()
			->where('user_id', $user->id)
			->exists();
	}

	public function membershipFor(User $user): ?BusinessUnitMembership
	{
		return $this->memberships()
			->with(['role'])
			->where('user_id', $user->id)
			->first();
	}

	/**
	 * Branding efectivo por herencia simple: el último valor no vacío en la cadena root->...->self.
	 * (Si necesitas otra regla de herencia, aquí es donde se ajusta).
	 */
	public function effectiveBranding(): array
	{
		$chain = $this->ancestorChain();

		$textDark = null;
		$bgLight = null;
		$textLight = null;
		$bgDark = null;
		$logoFileId = null;

		foreach ($chain as $u)
		{
			foreach ([
				'branding_text_dark' => 'textDark',
				'branding_bg_light' => 'bgLight',
				'branding_text_light' => 'textLight',
				'branding_bg_dark' => 'bgDark',
			] as $field => $var)
			{
				$v = $u->{$field} ?? null;
				$v = is_null($v) ? null : trim((string) $v);
				if ($v !== null && $v !== '')
				{
					${$var} = $v;
				}
			}

			if (!is_null($u->branding_logo_file_id ?? null))
			{
				$logoFileId = (int) $u->branding_logo_file_id;
			}
		}

		$logoUrl = null;
		if ($logoFileId)
		{
			$f = File::query()->find($logoFileId);
			if ($f && method_exists($f, 'url'))
			{
				$logoUrl = $f->url();
			}
		}

		return [
			'text_dark' => $textDark ?? '',
			'bg_light' => $bgLight ?? '',
			'text_light' => $textLight ?? '',
			'bg_dark' => $bgDark ?? '',
			'logo_url' => $logoUrl,
		];
	}

	public function systemLogoConstraints(): array
	{
		// Placeholder estable para frontend (si luego quieres validar por aquí).
		return [
			'max_size_kb' => 2048,
			'allowed_mimes' => ['image/png', 'image/jpeg', 'image/webp'],
		];
	}
}
