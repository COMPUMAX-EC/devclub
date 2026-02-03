<?php
// app/Models/CapitatedMonthlyRecord.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitatedMonthlyRecord extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE      = 'active';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $table = 'capitados_monthly_records';

    protected $fillable = [
        'company_id',
        'product_id',
        'person_id',
        'contract_id',
        'coverage_month',
        'plan_version_id',
        'load_batch_id',
        'full_name',
        'sex',
        'age_reported',
        'residence_country_id',
        'repatriation_country_id',
        'price_base',
        'price_source',
        'age_surcharge_rule_id',
        'age_surcharge_percent',
        'age_surcharge_amount',
        'price_final',
        'status',
        'rolled_back_at',
        'rolled_back_by_user_id',
    ];

    protected $casts = [
        'company_id'             => 'integer',
        'product_id'             => 'integer',
        'person_id'              => 'integer',
        'contract_id'            => 'integer',
        'plan_version_id'        => 'integer',
        'load_batch_id'          => 'integer',
        'age_reported'           => 'integer',
        'coverage_month'         => 'date',
        'residence_country_id'   => 'integer',
        'repatriation_country_id'=> 'integer',
        'price_base'             => 'decimal:2',
        'age_surcharge_percent'  => 'decimal:2',
        'age_surcharge_amount'   => 'decimal:2',
        'price_final'            => 'decimal:2',
        'status'                 => 'string',
        'rolled_back_at'         => 'datetime',
        'rolled_back_by_user_id' => 'integer',
    ];

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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(CapitatedContract::class, 'contract_id');
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'plan_version_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CapitatedBatchLog::class, 'load_batch_id');
    }

    public function residenceCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'residence_country_id');
    }

    public function repatriationCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'repatriation_country_id');
    }
}
