<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PassKitDataChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_log_id',
        'account_id',
        'table_name',
        'column_name',
        'old_value',
        'new_value',
        'change_type',
        'data_classification',
        'pii_data',
        'financial_data',
        'sensitive_data',
        'impact_level',
        'requires_notification',
        'affected_systems',
        'downstream_impacts',
        'requires_approval',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rollback_available',
        'rollback_expires_at',
        'rollback_instructions',
    ];

    protected $casts = [
        'affected_systems' => 'array',
        'downstream_impacts' => 'array',
        'rollback_instructions' => 'array',
        'pii_data' => 'boolean',
        'financial_data' => 'boolean',
        'sensitive_data' => 'boolean',
        'requires_notification' => 'boolean',
        'requires_approval' => 'boolean',
        'rollback_available' => 'boolean',
        'approved_at' => 'datetime',
        'rollback_expires_at' => 'datetime',
    ];

    // Relationships
    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(PassKitAuditLog::class, 'audit_log_id');
    }

    // Scopes
    public function scopeByTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    public function scopeByColumn($query, string $columnName)
    {
        return $query->where('column_name', $columnName);
    }

    public function scopeByChangeType($query, string $changeType)
    {
        return $query->where('change_type', $changeType);
    }

    public function scopeSensitiveData($query)
    {
        return $query->where('sensitive_data', true);
    }

    public function scopePiiData($query)
    {
        return $query->where('pii_data', true);
    }

    public function scopeFinancialData($query)
    {
        return $query->where('financial_data', true);
    }

    public function scopeHighImpact($query)
    {
        return $query->whereIn('impact_level', ['high', 'critical']);
    }

    public function scopeRequiresApproval($query)
    {
        return $query->where('requires_approval', true);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('requires_approval', true)
                    ->whereNull('approved_at');
    }

    public function scopeRollbackAvailable($query)
    {
        return $query->where('rollback_available', true)
                    ->where('rollback_expires_at', '>', now());
    }

    // Accessors
    public function getIsApprovedAttribute(): bool
    {
        return $this->requires_approval && $this->approved_at !== null;
    }

    public function getIsPendingApprovalAttribute(): bool
    {
        return $this->requires_approval && $this->approved_at === null;
    }

    public function getCanRollbackAttribute(): bool
    {
        return $this->rollback_available && 
               $this->rollback_expires_at && 
               $this->rollback_expires_at->isFuture();
    }

    public function getRollbackTimeRemainingAttribute(): ?string
    {
        if (!$this->can_rollback) {
            return null;
        }
        
        $diff = now()->diffInHours($this->rollback_expires_at);
        
        if ($diff < 24) {
            return $diff . ' hours';
        }
        
        return now()->diffInDays($this->rollback_expires_at) . ' days';
    }

    public function getValueDiffAttribute(): array
    {
        return [
            'old' => $this->formatValue($this->old_value),
            'new' => $this->formatValue($this->new_value),
            'changed' => $this->old_value !== $this->new_value,
        ];
    }

    // Methods
    public function approve(int $userId, string $notes = null): void
    {
        $this->update([
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    public function executeRollback(): bool
    {
        if (!$this->can_rollback) {
            return false;
        }
        
        // This would implement the actual rollback logic
        // For now, we'll just mark as rolled back
        $this->update([
            'rollback_available' => false,
            'rollback_expires_at' => now(),
        ]);
        
        return true;
    }

    protected function formatValue($value): string
    {
        if (is_null($value)) {
            return '[NULL]';
        }
        
        if (is_bool($value)) {
            return $value ? '[TRUE]' : '[FALSE]';
        }
        
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }
        
        if (strlen($value) > 100) {
            return substr($value, 0, 100) . '...';
        }
        
        return (string) $value;
    }

    // Static methods
    public static function getSensitiveChanges(int $accountId, int $days = 7): array
    {
        return self::where('account_id', $accountId)
                   ->where('created_at', '>=', now()->subDays($days))
                   ->sensitiveData()
                   ->with('auditLog')
                   ->orderBy('created_at', 'desc')
                   ->get()
                   ->toArray();
    }

    public static function getPendingApprovals(int $accountId): array
    {
        return self::where('account_id', $accountId)
                   ->pendingApproval()
                   ->with('auditLog')
                   ->orderBy('created_at', 'desc')
                   ->get()
                   ->toArray();
    }
}