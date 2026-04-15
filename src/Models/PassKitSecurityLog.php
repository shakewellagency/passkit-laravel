<?php

namespace ShakewellAgency\PassKitLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PassKitSecurityLog extends Model
{
    use HasFactory;

    protected $table = 'passkit_security_logs';

    protected $fillable = [
        'audit_log_id',
        'account_id',
        'security_event',
        'threat_level',
        'source_ip',
        'geolocation',
        'user_agent',
        'attack_pattern',
        'automated_detection',
        'detection_rule',
        'confidence_score',
        'response_action',
        'automatic_response',
        'mitigation_steps',
        'requires_investigation',
        'assigned_to',
        'investigation_status',
        'investigation_started_at',
        'investigation_completed_at',
        'investigation_notes',
        'regulatory_reportable',
        'regulatory_requirements',
        'reported_at',
        'report_reference',
    ];

    protected $casts = [
        'geolocation' => 'array',
        'regulatory_requirements' => 'array',
        'confidence_score' => 'decimal:2',
        'automated_detection' => 'boolean',
        'automatic_response' => 'boolean',
        'requires_investigation' => 'boolean',
        'regulatory_reportable' => 'boolean',
        'investigation_started_at' => 'datetime',
        'investigation_completed_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

    // Relationships
    public function auditLog(): BelongsTo
    {
        return $this->belongsTo(PassKitAuditLog::class, 'audit_log_id');
    }

    // Scopes
    public function scopeByThreatLevel($query, string $level)
    {
        return $query->where('threat_level', $level);
    }

    public function scopeHighThreat($query)
    {
        return $query->whereIn('threat_level', ['high', 'critical']);
    }

    public function scopeRequiresInvestigation($query)
    {
        return $query->where('requires_investigation', true);
    }

    public function scopeUnderInvestigation($query)
    {
        return $query->where('investigation_status', 'in_progress');
    }

    public function scopeBySecurityEvent($query, string $event)
    {
        return $query->where('security_event', $event);
    }

    public function scopeFromIp($query, string $ip)
    {
        return $query->where('source_ip', $ip);
    }

    public function scopeRegulatoryReportable($query)
    {
        return $query->where('regulatory_reportable', true);
    }

    // Accessors
    public function getIsHighThreatAttribute(): bool
    {
        return in_array($this->threat_level, ['high', 'critical']);
    }

    public function getIsUnderInvestigationAttribute(): bool
    {
        return $this->investigation_status === 'in_progress';
    }

    public function getInvestigationDurationAttribute(): ?int
    {
        if (!$this->investigation_started_at) {
            return null;
        }
        
        $endTime = $this->investigation_completed_at ?? now();
        return $this->investigation_started_at->diffInHours($endTime);
    }

    // Methods
    public function startInvestigation(int $assignedTo): void
    {
        $this->update([
            'assigned_to' => $assignedTo,
            'investigation_status' => 'in_progress',
            'investigation_started_at' => now(),
        ]);
    }

    public function completeInvestigation(string $notes, bool $falsePositive = false): void
    {
        $this->update([
            'investigation_status' => $falsePositive ? 'false_positive' : 'resolved',
            'investigation_completed_at' => now(),
            'investigation_notes' => $notes,
        ]);
    }

    public function markReported(string $reference): void
    {
        $this->update([
            'reported_at' => now(),
            'report_reference' => $reference,
        ]);
    }
}