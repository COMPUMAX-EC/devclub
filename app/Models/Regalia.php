<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Regalia extends Model
{
    protected $table = 'regalias';

    protected $fillable = [
        'source_type',
        'source_id',
        'beneficiary_user_id',
        'commission',
    ];

    protected $casts = [
        'commission' => 'float',
    ];

    /**
     * Usuario beneficiario de la regalía.
     */
    public function beneficiary()
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    /**
     * Origen tipo usuario (solo válido si source_type = 'user').
     */
    public function originUser()
    {
        return $this->belongsTo(User::class, 'source_id');
    }

    /**
     * Origen tipo unidad (solo válido si source_type = 'unit').
     */
    public function originUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'source_id');
    }
}
