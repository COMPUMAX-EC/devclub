<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessUnitMembership extends Model
{
    protected $table = 'memberships_business_unit';

    protected $fillable = [
        'business_unit_id',
        'user_id',
        'role_id',
    ];

    protected $casts = [
        'business_unit_id' => 'integer',
        'user_id' => 'integer',
        'role_id' => 'integer',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
