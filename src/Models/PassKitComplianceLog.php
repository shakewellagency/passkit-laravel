<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PassKitComplianceLog extends Model
{
    use HasFactory;

    protected $table = 'passkit_compliance_logs';

    protected $fillable = [
        'audit_log_id',
        'account_id',
        'regulation',
        'compliance_requirement',
        'control_objective',
        'compliance_status',
        'compliance_score',
        'compliance_notes',
        'compliance_evidence',
        'risk_level',
        'risk_description',
        'risk_factors',
        'mitigation_measures',
        'auditor',
        'audit_date',
        'audit_reference',
        'audit_findings',
        'requires_remediation',
        'remediation_plan',
        'remediation_deadline',
        'assigned_to',
        'remediation_status',
    ];

    protected $casts = [
        'compliance_evidence' => 'array',
        'risk_factors' => 'array',
        'compliance_score' => 'decimal:2',
        'requires_remediation' => 'boolean',
        'audit_date' => 'datetime',
        'remediation_deadline' => 'datetime',
    ];

    // Relationships
    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(PassKitAuditLog::class, 'audit_log_id');
    }

    // Scopes
    public function scopeByRegulation($query, string $regulation)
    {
        return $query->where('regulation', $regulation);
    }

    public function scopeByComplianceStatus($query, string $status)
    {
        return $query->where('compliance_status', $status);
    }

    public function scopeNonCompliant($query)
    {
        return $query->where('compliance_status', 'non_compliant');
    }

    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    public function scopeRequiresRemediation($query)
    {
        return $query->where('requires_remediation', true);
    }

    public function scopeOverdueRemediation($query)
    {
        return $query->where('requires_remediation', true)
                    ->where('remediation_deadline', '<', now())
                    ->whereNotIn('remediation_status', ['completed']);
    }

    // Accessors
    public function getIsCompliantAttribute(): bool
    {
        return $this->compliance_status === 'compliant';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->requires_remediation && 
               $this->remediation_deadline && 
               $this->remediation_deadline->isPast() &&
               $this->remediation_status !== 'completed';
    }

    public function getRemediationTimeRemainingAttribute(): ?string
    {
        if (!$this->requires_remediation || !$this->remediation_deadline) {
            return null;
        }
        
        if ($this->remediation_deadline->isPast()) {
            return 'OVERDUE';
        }
        
        $diff = now()->diffInDays($this->remediation_deadline);
        
        if ($diff < 1) {
            return now()->diffInHours($this->remediation_deadline) . ' hours';
        }
        
        return $diff . ' days';
    }

    // Methods
    public function markCompliant(string $notes = null, array $evidence = []): void
    {
        $this->update([
            'compliance_status' => 'compliant',
            'compliance_notes' => $notes,
            'compliance_evidence' => array_merge($this->compliance_evidence ?? [], $evidence),
            'requires_remediation' => false,
            'remediation_status' => 'completed',
        ]);
    }

    public function startRemediation(int $assignedTo, string $plan): void
    {
        $this->update([
            'assigned_to' => $assignedTo,
            'remediation_plan' => $plan,
            'remediation_status' => 'in_progress',
        ]);
    }

    public function completeRemediation(string $notes = null): void
    {
        $this->update([
            'remediation_status' => 'completed',
            'compliance_notes' => $notes,
        ]);
    }

    // Static methods
    public static function getComplianceSummary(int $accountId, int $days = 30): array
    {
        $query = self::where('account_id', $accountId)
                     ->where('created_at', '>=', now()->subDays($days));
        
        return [
            'total_checks' => $query->count(),
            'compliant' => $query->where('compliance_status', 'compliant')->count(),
            'non_compliant' => $query->nonCompliant()->count(),
            'partial_compliant' => $query->where('compliance_status', 'partial')->count(),
            'high_risk' => $query->highRisk()->count(),
            'requires_remediation' => $query->requiresRemediation()->count(),
            'overdue_remediation' => $query->overdueRemediation()->count(),
        ];
    }

    public static function getRegulationStats(int $accountId): array
    {
        return self::where('account_id', $accountId)
                   ->selectRaw('regulation, 
                              COUNT(*) as total_checks,
                              SUM(CASE WHEN compliance_status = "compliant" THEN 1 ELSE 0 END) as compliant_count,
                              AVG(compliance_score) as avg_score')
                   ->groupBy('regulation')
                   ->orderBy('total_checks', 'desc')
                   ->get()
                   ->toArray();
    }
}