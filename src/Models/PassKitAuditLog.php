<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PassKitAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'event_type',
        'entity_type',
        'entity_id',
        'passkit_id',
        'user_id',
        'user_type',
        'source',
        'ip_address',
        'user_agent',
        'operation',
        'description',
        'status',
        'error_message',
        'error_details',
        'old_values',
        'new_values',
        'metadata',
        'request_data',
        'response_data',
        'execution_time_ms',
        'correlation_id',
        'session_id',
        'batch_id',
        'passkit_operation',
        'passkit_response_code',
        'passkit_response_time_ms',
        'passkit_request_headers',
        'passkit_response_headers',
        'data_validated',
        'validation_errors',
        'checksum',
        'sensitive_data_masked',
        'workflow_step',
        'business_context',
        'business_impact_score',
        'tags',
        'security_level',
        'requires_approval',
        'approved_by',
        'approved_at',
        'gdpr_relevant',
        'pci_relevant',
        'expires_at',
        'archived',
        'archived_at',
        'archive_reason',
    ];

    protected $casts = [
        'error_details' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
        'passkit_request_headers' => 'array',
        'passkit_response_headers' => 'array',
        'validation_errors' => 'array',
        'tags' => 'array',
        'execution_time_ms' => 'decimal:3',
        'passkit_response_time_ms' => 'decimal:3',
        'business_impact_score' => 'decimal:2',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
        'archived_at' => 'datetime',
        'data_validated' => 'boolean',
        'sensitive_data_masked' => 'boolean',
        'requires_approval' => 'boolean',
        'gdpr_relevant' => 'boolean',
        'pci_relevant' => 'boolean',
        'archived' => 'boolean',
    ];

    // Relationships
    public function securityLog(): HasOne
    {
        return $this->hasOne(PassKitSecurityLog::class, 'audit_log_id');
    }

    public function performanceLog(): HasOne
    {
        return $this->hasOne(PassKitPerformanceLog::class, 'audit_log_id');
    }

    public function dataChanges(): HasMany
    {
        return $this->hasMany(PassKitDataChange::class, 'audit_log_id');
    }

    public function complianceLog(): HasOne
    {
        return $this->hasOne(PassKitComplianceLog::class, 'audit_log_id');
    }

    // Scopes
    public function scopeByAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeBySecurityLevel($query, string $level)
    {
        return $query->where('security_level', $level);
    }

    public function scopeHighRisk($query)
    {
        return $query->whereIn('security_level', ['high', 'critical']);
    }

    public function scopeRecentDays($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByCorrelationId($query, string $correlationId)
    {
        return $query->where('correlation_id', $correlationId);
    }

    public function scopeByBatchId($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeRequiresApproval($query)
    {
        return $query->where('requires_approval', true)
                    ->whereNull('approved_at');
    }

    public function scopeGdprRelevant($query)
    {
        return $query->where('gdpr_relevant', true);
    }

    public function scopePciRelevant($query)
    {
        return $query->where('pci_relevant', true);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('archived', false);
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    public function scopeSlowOperations($query, float $thresholdMs = 5000)
    {
        return $query->where('execution_time_ms', '>', $thresholdMs);
    }

    // Accessors
    public function getIsHighRiskAttribute(): bool
    {
        return in_array($this->security_level, ['high', 'critical']);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->requires_approval && $this->approved_at !== null;
    }

    public function getHasSecurityEventAttribute(): bool
    {
        return $this->securityLog !== null;
    }

    public function getHasPerformanceIssueAttribute(): bool
    {
        return $this->performanceLog && $this->performanceLog->performance_threshold_exceeded;
    }

    public function getFormattedExecutionTimeAttribute(): string
    {
        if ($this->execution_time_ms < 1000) {
            return number_format($this->execution_time_ms, 2) . 'ms';
        }
        
        return number_format($this->execution_time_ms / 1000, 2) . 's';
    }

    public function getRiskScoreAttribute(): float
    {
        $score = 0.0;
        
        // Base risk from security level
        $securityScores = [
            'low' => 10,
            'normal' => 25,
            'high' => 60,
            'critical' => 90,
        ];
        
        $score += $securityScores[$this->security_level] ?? 25;
        
        // Add risk for failed operations
        if ($this->status === 'failed') {
            $score += 20;
        }
        
        // Add risk for high business impact
        if ($this->business_impact_score > 50) {
            $score += 15;
        }
        
        // Add risk for PII/financial data
        if ($this->gdpr_relevant || $this->pci_relevant) {
            $score += 10;
        }
        
        return min(100.0, $score);
    }

    // Methods
    public function approve(int $userId, string $notes = null): void
    {
        $this->update([
            'approved_by' => $userId,
            'approved_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'approval_notes' => $notes,
                'approval_timestamp' => now()->toISOString(),
            ]),
        ]);
    }

    public function archive(string $reason = null): void
    {
        $this->update([
            'archived' => true,
            'archived_at' => now(),
            'archive_reason' => $reason,
        ]);
    }

    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        $this->update(['tags' => $tags]);
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    public function getRelatedLogs(): array
    {
        return self::where('correlation_id', $this->correlation_id)
                   ->where('id', '!=', $this->id)
                   ->orderBy('created_at')
                   ->get()
                   ->toArray();
    }

    public function getBatchLogs(): array
    {
        if (!$this->batch_id) {
            return [];
        }
        
        return self::where('batch_id', $this->batch_id)
                   ->where('id', '!=', $this->id)
                   ->orderBy('created_at')
                   ->get()
                   ->toArray();
    }

    // Static methods
    public static function getEventTypeStats(int $accountId, int $days = 7): array
    {
        return self::byAccount($accountId)
                   ->recentDays($days)
                   ->selectRaw('event_type, COUNT(*) as count, AVG(execution_time_ms) as avg_time')
                   ->groupBy('event_type')
                   ->orderBy('count', 'desc')
                   ->get()
                   ->toArray();
    }

    public static function getSecuritySummary(int $accountId, int $days = 7): array
    {
        $query = self::byAccount($accountId)->recentDays($days);
        
        return [
            'total_events' => $query->count(),
            'high_risk_events' => $query->highRisk()->count(),
            'failed_events' => $query->failed()->count(),
            'security_events' => $query->byEventType('security_event')->count(),
            'gdpr_events' => $query->gdprRelevant()->count(),
            'pci_events' => $query->pciRelevant()->count(),
            'requires_approval' => $query->requiresApproval()->count(),
        ];
    }

    public static function getPerformanceSummary(int $accountId, int $days = 7): array
    {
        $query = self::byAccount($accountId)->recentDays($days);
        
        return [
            'avg_execution_time' => $query->avg('execution_time_ms'),
            'max_execution_time' => $query->max('execution_time_ms'),
            'slow_operations' => $query->slowOperations()->count(),
            'total_operations' => $query->count(),
            'success_rate' => $query->successful()->count() / max(1, $query->count()) * 100,
        ];
    }
}