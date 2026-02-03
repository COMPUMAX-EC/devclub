<?php
// app/Models/CapitatedContract.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CapitatedContract extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE      = 'active';
    public const STATUS_EXPIRED     = 'expired';
    public const STATUS_VOIDED      = 'voided';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $table = 'capitados_contracts';

    protected $fillable = [
        'company_id',
        'product_id',
        'person_id',
        'status',
        'entry_date',
        'valid_until',
        'entry_age',
        'wtime_suicide_ends_at',
        'wtime_preexisting_conditions_ends_at',
        'wtime_accident_ends_at',
        'terminated_at',
        'termination_reason',
        'rolled_back_at',
        'rolled_back_by_user_id',
        'uuid',
    ];

    protected $casts = [
        'company_id'                        => 'integer',
        'product_id'                        => 'integer',
        'person_id'                         => 'integer',
        'entry_age'                         => 'integer',
        'entry_date'                        => 'date',
        'valid_until'                       => 'date',
        'wtime_suicide_ends_at'            => 'date',
        'wtime_preexisting_conditions_ends_at' => 'date',
        'wtime_accident_ends_at'           => 'date',
        'terminated_at'                     => 'datetime',
        'rolled_back_at'                    => 'datetime',
        'rolled_back_by_user_id'           => 'integer',
    ];

    /**
     * Genera el uuid automáticamente al crear el contrato
     * si aún no viene seteado.
     */
    protected static function booted(): void
    {
        static::creating(function (CapitatedContract $contract) {
            if (empty($contract->uuid)) {
                $contract->uuid = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(CapitatedProductInsured::class, 'person_id');
    }

    public function monthlyRecords(): HasMany
    {
        return $this->hasMany(CapitatedMonthlyRecord::class, 'contract_id');
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isRolledBack(): bool
    {
        return $this->status === self::STATUS_ROLLED_BACK;
    }

    public function canBeExtended(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_EXPIRED], true);
    }
}
