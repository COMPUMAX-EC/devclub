<?php
// app/Models/CapitatedBatchItemLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitatedBatchItemLog extends Model
{
    use HasFactory;

    protected $table = 'capitados_batch_item_logs';

    protected $fillable = [
        'batch_id',
        'sheet_name',
        'row_number',
        'product_id',
        'plan_version_id',
        'residence_raw',
        'residence_code_extracted',
        'repatriation_raw',
        'repatriation_code_extracted',
        'residence_country_id',
        'repatriation_country_id',
        'document_number',
        'full_name',
        'sex',
        'age_reported',
        'result',
        'rejection_code',
        'rejection_detail',
        'person_id',
        'contract_id',
        'monthly_record_id',
        'duplicated_record_id',
    ];

    protected $casts = [
        'batch_id'               => 'integer',
        'row_number'             => 'integer',
        'product_id'             => 'integer',
        'plan_version_id'        => 'integer',
        'residence_country_id'   => 'integer',
        'repatriation_country_id'=> 'integer',
        'age_reported'           => 'integer',
        'person_id'              => 'integer',
        'contract_id'            => 'integer',
        'monthly_record_id'      => 'integer',
        'duplicated_record_id'   => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CapitatedBatchLog::class, 'batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'plan_version_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(CapitatedProductInsured::class, 'person_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(CapitatedContract::class, 'contract_id');
    }

    public function monthlyRecord(): BelongsTo
    {
        return $this->belongsTo(CapitatedMonthlyRecord::class, 'monthly_record_id');
    }

    public function duplicatedRecord(): BelongsTo
    {
        return $this->belongsTo(CapitatedMonthlyRecord::class, 'duplicated_record_id');
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
