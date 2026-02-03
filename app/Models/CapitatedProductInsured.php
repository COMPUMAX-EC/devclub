<?php
// app/Models/CapitatedProductInsured.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapitatedProductInsured extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE      = 'active';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $table = 'capitados_product_insureds';

    protected $fillable = [
        'company_id',
        'product_id',
        'document_number',
        'full_name',
        'sex',
        'residence_country_id',
        'repatriation_country_id',
        'age_reported',
        'status',
        'rolled_back_at',
        'rolled_back_by_user_id',
    ];

    protected $casts = [
        'company_id'             => 'integer',
        'product_id'             => 'integer',
        'residence_country_id'   => 'integer',
        'repatriation_country_id'=> 'integer',
        'age_reported'           => 'integer',
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

    public function residenceCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'residence_country_id');
    }

    public function repatriationCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'repatriation_country_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(CapitatedContract::class, 'person_id');
    }

    public function monthlyRecords(): HasMany
    {
        return $this->hasMany(CapitatedMonthlyRecord::class, 'person_id');
    }
}
