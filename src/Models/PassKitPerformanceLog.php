<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PassKitPerformanceLog extends Model
{
    use HasFactory;

    protected $table = 'passkit_performance_logs';

    protected $fillable = [
        'audit_log_id',
        'account_id',
        'operation_type',
        'duration_ms',
        'api_call_time_ms',
        'database_time_ms',
        'validation_time_ms',
        'memory_usage_bytes',
        'peak_memory_usage_bytes',
        'cpu_usage_percent',
        'database_queries_count',
        'records_processed',
        'records_per_second',
        'data_size_bytes',
        'data_transfer_rate_mbps',
        'success_rate_percent',
        'retry_count',
        'cache_hit',
        'cache_hit_rate_percent',
        'performance_threshold_exceeded',
        'performance_grade',
        'performance_score',
        'performance_details',
        'system_load_average',
        'concurrent_operations',
        'server_instance',
        'database_instance',
    ];

    protected $casts = [
        'duration_ms' => 'float',
        'api_call_time_ms' => 'float',
        'database_time_ms' => 'float',
        'validation_time_ms' => 'float',
        'records_per_second' => 'float',
        'data_transfer_rate_mbps' => 'float',
        'success_rate_percent' => 'float',
        'cache_hit_rate_percent' => 'float',
        'performance_score' => 'decimal:2',
        'system_load_average' => 'decimal:2',
        'performance_details' => 'array',
        'performance_threshold_exceeded' => 'boolean',
        'cache_hit' => 'boolean',
    ];

    // Relationships
    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(PassKitAuditLog::class, 'audit_log_id');
    }

    // Scopes
    public function scopeByOperationType($query, string $type)
    {
        return $query->where('operation_type', $type);
    }

    public function scopeSlowOperations($query, float $thresholdMs = 5000)
    {
        return $query->where('duration_ms', '>', $thresholdMs);
    }

    public function scopeThresholdExceeded($query)
    {
        return $query->where('performance_threshold_exceeded', true);
    }

    public function scopeByGrade($query, string $grade)
    {
        return $query->where('performance_grade', $grade);
    }

    public function scopePoorPerformance($query)
    {
        return $query->whereIn('performance_grade', ['D', 'F']);
    }

    public function scopeRecentDays($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Accessors
    public function getIsSlowOperationAttribute(): bool
    {
        return $this->duration_ms > 5000;
    }

    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_ms < 1000) {
            return number_format($this->duration_ms, 2) . 'ms';
        }
        
        return number_format($this->duration_ms / 1000, 2) . 's';
    }

    public function getFormattedMemoryUsageAttribute(): string
    {
        if (!$this->memory_usage_bytes) {
            return 'N/A';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = $this->memory_usage_bytes;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getEfficiencyScoreAttribute(): float
    {
        if (!$this->records_processed || !$this->duration_ms) {
            return 0.0;
        }
        
        $baseScore = min(100.0, ($this->records_processed / ($this->duration_ms / 1000)) * 10);
        
        // Adjust for success rate
        if ($this->success_rate_percent) {
            $baseScore *= ($this->success_rate_percent / 100);
        }
        
        // Bonus for good cache performance
        if ($this->cache_hit_rate_percent && $this->cache_hit_rate_percent > 80) {
            $baseScore *= 1.1;
        }
        
        return min(100.0, $baseScore);
    }

    // Static methods
    public static function getPerformanceTrends(int $accountId, int $days = 30): array
    {
        $data = self::where('account_id', $accountId)
                    ->where('created_at', '>=', now()->subDays($days))
                    ->selectRaw('DATE(created_at) as date, 
                               AVG(duration_ms) as avg_duration,
                               AVG(performance_score) as avg_score,
                               COUNT(*) as operation_count')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
        
        return $data->toArray();
    }

    public static function getOperationStats(int $accountId, int $days = 7): array
    {
        return self::where('account_id', $accountId)
                   ->where('created_at', '>=', now()->subDays($days))
                   ->selectRaw('operation_type,
                              COUNT(*) as count,
                              AVG(duration_ms) as avg_duration,
                              MAX(duration_ms) as max_duration,
                              AVG(performance_score) as avg_score')
                   ->groupBy('operation_type')
                   ->orderBy('count', 'desc')
                   ->get()
                   ->toArray();
    }
}