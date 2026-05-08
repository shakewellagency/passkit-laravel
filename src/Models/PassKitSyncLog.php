<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassKitSyncLog extends Model
{
    use HasFactory;

    protected $table = 'passkit_sync_logs';

    protected $fillable = [
        'account_id',
        'sync_type',
        'sync_direction',
        'status',
        'program_id',
        'entity_type',
        'entity_id',
        'total_records',
        'processed_records',
        'successful_records',
        'failed_records',
        'skipped_records',
        'started_at',
        'completed_at',
        'duration_seconds',
        'records_per_second',
        'error_message',
        'error_details',
        'failed_record_ids',
        'sync_from_date',
        'sync_to_date',
        'sync_filters',
        'sync_options',
        'api_requests_made',
        'api_requests_failed',
        'avg_api_response_time_ms',
        'changes_summary',
        'detailed_log',
        'triggered_by',
        'trigger_source',
        'next_sync_at',
        'sync_frequency',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'sync_from_date' => 'datetime',
        'sync_to_date' => 'datetime',
        'next_sync_at' => 'datetime',
        'error_details' => 'array',
        'failed_record_ids' => 'array',
        'sync_filters' => 'array',
        'sync_options' => 'array',
        'changes_summary' => 'array',
        'total_records' => 'integer',
        'processed_records' => 'integer',
        'successful_records' => 'integer',
        'failed_records' => 'integer',
        'skipped_records' => 'integer',
        'duration_seconds' => 'integer',
        'api_requests_made' => 'integer',
        'api_requests_failed' => 'integer',
        'records_per_second' => 'decimal:2',
        'avg_api_response_time_ms' => 'decimal:3',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(PassKitProgram::class, 'program_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeOfType($query, string $syncType)
    {
        return $query->where('sync_type', $syncType);
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }
}
