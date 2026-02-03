<?php

namespace App\Models;

use App\Casts\TranslatableJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'company_id',
        'status',
        'name',
        'description',
        'product_type',
        'show_in_widget',
    ];

    protected $casts = [
        'company_id'     => 'integer',
        'name'           => TranslatableJson::class,
        'description'    => TranslatableJson::class,
        'show_in_widget' => 'boolean',
    ];

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_PLAN_REGULAR  = 'plan_regular';
    public const TYPE_PLAN_CAPITADO = 'plan_capitado';

    public const EDIT_ROUTES = [
        self::TYPE_PLAN_REGULAR  => 'admin.products.plans.index',
        self::TYPE_PLAN_CAPITADO => 'admin.products.plans.index',
    ];

    public static function productTypes(): array
    {
        return [
            self::TYPE_PLAN_REGULAR,
            self::TYPE_PLAN_CAPITADO,
        ];
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('product_type', $type);
    }

    public function planVersions(): HasMany
    {
        return $this->hasMany(PlanVersion::class)->orderBy('id');
    }

    /**
     * Devuelve la versión activa del plan (si existe).
     *
     * Regla del dominio: solo puede existir UNA versión activa por producto.
     */
    public function activePlanVersion(): HasOne
    {
        return $this->hasOne(PlanVersion::class)
            ->where('status', PlanVersion::STATUS_ACTIVE)
            ->orderByDesc('id');
    }

    /**
     * Indica si el producto tiene una versión activa.
     *
     * - Si la relación viene eager loaded, no dispara query adicional.
     * - Si no viene cargada, usa exists().
     */
    public function hasActivePlanVersion(): bool
    {
        if ($this->relationLoaded('activePlanVersion')) {
            return (bool) $this->getRelation('activePlanVersion');
        }

        return $this->activePlanVersion()->exists();
    }

    public function isPlan(): bool
    {
        return in_array($this->product_type, ['plan_regular', 'plan_capitado'], true);
    }

    public function editRouteName(): string
    {
        return self::EDIT_ROUTES[$this->product_type] ?? 'admin.products.index';
    }

    public function editRouteParams(): array
    {
        if (isset(self::EDIT_ROUTES[$this->product_type])) {
            return ['product' => $this->id];
        }

        return [];
    }

    public function editUrl(): string
    {
        return route($this->editRouteName(), $this->editRouteParams());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
