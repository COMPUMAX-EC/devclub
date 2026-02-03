<?php
// app/Models/CapitatedBatchLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapitatedBatchLog extends Model
{
    use HasFactory;

    public const STATUS_DRAFT          = 'draft';
    public const STATUS_PROCESSED      = 'processed';
    public const STATUS_PROCESSED_ZERO = 'processed_zero';
    public const STATUS_FAILED         = 'failed';
    public const STATUS_ROLLED_BACK    = 'rolled_back';

    protected $table = 'capitados_batch_logs';

    protected $fillable = [
        'company_id',
        'coverage_month',
        'source',
        'source_file_id',
        'original_filename',
        'file_hash',
        'created_by_user_id',
        'status',
        'processed_at',
        'total_rows',
        'total_applied',
        'total_rejected',
        'total_duplicated',
        'total_incongruences',
        'total_plan_errors',
        'total_rolled_back',
        'is_any_month_allowed',
        'cutoff_day',
        'error_summary',
        'summary_json',
        'rolled_back_at',
        'rolled_back_by_user_id',
    ];

    protected $casts = [
        'company_id'          => 'integer',
        'coverage_month'      => 'date',
        'source_file_id'      => 'integer',
        'created_by_user_id'  => 'integer',
        'processed_at'        => 'datetime',
        'total_rows'          => 'integer',
        'total_applied'       => 'integer',
        'total_rejected'      => 'integer',
        'total_duplicated'    => 'integer',
        'total_incongruences' => 'integer',
        'total_plan_errors'   => 'integer',
        'total_rolled_back'   => 'integer',
        'is_any_month_allowed'=> 'boolean',
        'cutoff_day'          => 'integer',
        'summary_json'        => 'array',
        'rolled_back_at'      => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'source_file_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CapitatedBatchItemLog::class, 'batch_id');
    }

    public function monthlyRecords(): HasMany
    {
        return $this->hasMany(CapitatedMonthlyRecord::class, 'load_batch_id');
    }
}
