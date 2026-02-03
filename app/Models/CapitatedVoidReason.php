<?php
// app/Models/CapitatedVoidReason.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CapitatedVoidReason extends Model
{
    use HasFactory;

    protected $table = 'capitados_void_reasons';

    protected $fillable = [
        'label',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
