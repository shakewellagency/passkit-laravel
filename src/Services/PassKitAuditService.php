<?php

namespace ShakewellAgency\PassKitLaravel\Services;

use ShakewellAgency\PassKitLaravel\Models\PassKitAuditLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitSecurityLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitPerformanceLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class PassKitAuditService
{
    public function log(array $data): PassKitAuditLog
    {
        $createdAt = $data['created_at'] ?? null;
        $attrs = $this->normalizeLogAttributes($data);

        $log = PassKitAuditLog::create($attrs);

        if ($createdAt !== null) {
            $log->created_at = $createdAt;
            $log->saveQuietly();
        }

        return $log->refresh();
    }

    public function logSecurityEvent(array $data): PassKitAuditLog
    {
        $data['event_type'] = $data['event_type'] ?? 'security_event';
        $data['security_level'] = $data['security_level'] ?? 'normal';

        $log = $this->log($data);

        PassKitSecurityLog::create([
            'audit_log_id' => $log->id,
            'account_id' => $log->account_id,
            'security_event' => $data['security_event'] ?? $data['event_type'],
            'threat_level' => $data['threat_level'] ?? 'low',
            'source_ip' => $data['source_ip'] ?? '0.0.0.0',
            'user_agent' => $data['user_agent'] ?? null,
            'risk_indicators' => $data['risk_indicators'] ?? null,
            'mitigation_actions' => $data['mitigation_actions'] ?? null,
        ]);

        return $log->fresh(['securityLog']);
    }

    public function logPerformanceEvent(array $data): PassKitAuditLog
    {
        $data['event_type'] = $data['event_type'] ?? 'performance_event';

        $log = $this->log($data);

        PassKitPerformanceLog::create([
            'audit_log_id' => $log->id,
            'account_id' => $log->account_id,
            'operation_type' => $data['operation_type'] ?? 'unknown',
            'duration_ms' => $data['execution_time_ms'] ?? 0,
            'memory_usage_mb' => $data['memory_usage_mb'] ?? null,
            'cpu_usage_percent' => $data['cpu_usage_percent'] ?? null,
            'performance_threshold_exceeded' => $data['performance_threshold_exceeded'] ?? false,
            'performance_grade' => $data['performance_grade'] ?? null,
            'performance_score' => $data['performance_score'] ?? null,
        ]);

        return $log->fresh(['performanceLog']);
    }

    public function logChange(array $data): PassKitAuditLog
    {
        $data['event_type'] = $data['event_type'] ?? 'entity_changed';
        $data['operation'] = $data['operation'] ?? 'update';
        return $this->log($data);
    }

    public function detectSuspiciousPatterns(int $accountId): array
    {
        $failedLogins = PassKitSecurityLog::where('account_id', $accountId)
            ->where('security_event', 'failed_authentication')
            ->where('created_at', '>=', now()->subHour())
            ->get();

        return [
            'failed_logins' => [
                'count' => $failedLogins->count(),
                'source_ips' => $failedLogins->pluck('source_ip')->unique()->values()->all(),
            ],
        ];
    }

    public function assessChangeSignificance(PassKitAuditLog $log): array
    {
        $reasons = [];
        $old = $log->old_values ?? [];
        $new = $log->new_values ?? [];

        if (isset($old['points'], $new['points'])) {
            $delta = abs((int) $new['points'] - (int) $old['points']);
            if ($delta >= 1000) {
                $reasons[] = 'large_points_change';
            }
        }

        if (isset($old['status'], $new['status']) && $old['status'] !== $new['status']) {
            $reasons[] = 'status_change';
        }

        return [
            'is_significant' => !empty($reasons),
            'significance_reasons' => $reasons,
        ];
    }

    public function getEntityChangeHistory(string $entityType, $entityId): Collection
    {
        return PassKitAuditLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getSlowOperations(int $accountId, float $thresholdMs): Collection
    {
        return PassKitAuditLog::where('account_id', $accountId)
            ->where('execution_time_ms', '>=', $thresholdMs)
            ->get();
    }

    public function getPerformanceSummary(int $accountId, int $days = 7): array
    {
        $logs = PassKitAuditLog::where('account_id', $accountId)
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('execution_time_ms')
            ->get();

        $distribution = PassKitPerformanceLog::whereIn('audit_log_id', $logs->pluck('id'))
            ->selectRaw('performance_grade, COUNT(*) as count')
            ->groupBy('performance_grade')
            ->pluck('count', 'performance_grade')
            ->all();

        return [
            'avg_execution_time' => (float) ($logs->avg('execution_time_ms') ?? 0),
            'max_execution_time' => (float) ($logs->max('execution_time_ms') ?? 0),
            'slow_operations' => $logs->where('execution_time_ms', '>', 1000)->count(),
            'performance_distribution' => $distribution,
        ];
    }

    public function generateComplianceReport(int $accountId, array $options = []): array
    {
        $days = $options['period_days'] ?? 30;
        $base = PassKitAuditLog::where('account_id', $accountId)
            ->where('created_at', '>=', now()->subDays($days));

        $gdprCount = (clone $base)->where('gdpr_relevant', true)->count();
        $securityCount = PassKitSecurityLog::where('account_id', $accountId)
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
        $total = (clone $base)->count();
        $failed = (clone $base)->where('status', 'failed')->count();
        $score = $total > 0 ? max(0, 100 - (int) round(($failed / $total) * 100)) : 100;

        return [
            'period' => ['days' => $days, 'from' => now()->subDays($days)->toIso8601String(), 'to' => now()->toIso8601String()],
            'total_events' => $total,
            'gdpr_events' => $gdprCount,
            'security_events' => $securityCount,
            'compliance_score' => $score,
        ];
    }

    public function generateEntityAuditTrail(string $entityType, $entityId): array
    {
        return PassKitAuditLog::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($log) => [
                'event_type' => $log->event_type,
                'operation' => $log->operation,
                'status' => $log->status,
                'created_at' => $log->created_at?->toIso8601String(),
                'user_id' => $log->user_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
            ])
            ->all();
    }

    public function exportAuditLogs(int $accountId, array $options = []): array
    {
        $format = $options['format'] ?? 'csv';
        $dateFrom = $options['date_from'] ?? now()->subDays(30);
        $dateTo = $options['date_to'] ?? now();

        $logs = PassKitAuditLog::where('account_id', $accountId)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderBy('created_at')
            ->get();

        $csv = "id,event_type,entity_type,entity_id,status,created_at\n";
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s\n",
                $log->id,
                $log->event_type,
                $log->entity_type,
                $log->entity_id,
                $log->status,
                $log->created_at?->toIso8601String()
            );
        }

        return [
            'filename' => sprintf('audit-logs-%d-%s.%s', $accountId, now()->format('Ymd-His'), $format),
            'content' => $csv,
            'mime_type' => $format === 'csv' ? 'text/csv' : 'application/json',
        ];
    }

    public function searchLogs(int $accountId, array $options = []): Collection
    {
        $query = PassKitAuditLog::where('account_id', $accountId);

        if (!empty($options['query'])) {
            $term = $options['query'];
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', "%{$term}%")
                    ->orWhere('event_type', 'like', "%{$term}%");
            });
        }

        if (!empty($options['event_types'])) {
            $query->whereIn('event_type', $options['event_types']);
        }

        return $query->get();
    }

    public function getEventStatistics(int $accountId, int $days = 7): array
    {
        return PassKitAuditLog::where('account_id', $accountId)
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['event_type' => $row->event_type, 'count' => (int) $row->count])
            ->all();
    }

    public function analyzeUserActivity(int $userId, int $accountId): array
    {
        $logs = PassKitAuditLog::where('account_id', $accountId)
            ->where('user_id', $userId)
            ->get();

        $hours = $logs->groupBy(fn ($log) => (int) $log->created_at?->format('H'))
            ->map->count()
            ->all();

        $mostActive = !empty($hours) ? array_keys($hours, max($hours))[0] : null;

        return [
            'total_events' => $logs->count(),
            'events_by_hour' => $hours,
            'most_active_hour' => $mostActive,
            'activity_trend' => $logs->count() > 0 ? 'active' : 'inactive',
        ];
    }

    public function detectAnomalies(int $accountId): array
    {
        $logs = PassKitAuditLog::where('account_id', $accountId)
            ->whereNotNull('execution_time_ms')
            ->get();

        if ($logs->count() < 5) {
            return [];
        }

        $mean = $logs->avg('execution_time_ms');
        $stddev = sqrt($logs->map(fn ($l) => pow($l->execution_time_ms - $mean, 2))->avg());
        $threshold = $mean + (3 * $stddev);

        $anomalies = [];
        foreach ($logs as $log) {
            if ($log->execution_time_ms > $threshold && $log->execution_time_ms > 1000) {
                $anomalies[] = [
                    'type' => 'execution_time_anomaly',
                    'log_id' => $log->id,
                    'execution_time_ms' => $log->execution_time_ms,
                ];
            }
        }

        return $anomalies;
    }

    public function cleanupOldLogs(int $accountId, int $retentionDays): int
    {
        return PassKitAuditLog::where('account_id', $accountId)
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
    }

    public function archiveOldLogs(int $accountId, int $retentionDays): int
    {
        $logs = PassKitAuditLog::where('account_id', $accountId)
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->where('archived', false)
            ->get();

        foreach ($logs as $log) {
            $log->update(['archived' => true, 'archived_at' => now()]);
        }

        return $logs->count();
    }

    public function cleanupWithRetentionPolicies(int $accountId, array $policies): int
    {
        $gdprDays = $policies['gdpr_retention_days'] ?? 2555;
        $defaultDays = $policies['default_retention_days'] ?? 365;

        $deleted = 0;

        $deleted += PassKitAuditLog::where('account_id', $accountId)
            ->where('gdpr_relevant', true)
            ->where('created_at', '<', now()->subDays($gdprDays))
            ->delete();

        $deleted += PassKitAuditLog::where('account_id', $accountId)
            ->where('gdpr_relevant', false)
            ->where('created_at', '<', now()->subDays($defaultDays))
            ->delete();

        return $deleted;
    }

    protected function normalizeLogAttributes(array $data): array
    {
        $columns = [
            'account_id', 'event_type', 'entity_type', 'entity_id', 'passkit_id',
            'user_id', 'user_type', 'source', 'ip_address', 'user_agent',
            'operation', 'description', 'status', 'error_message', 'error_details',
            'old_values', 'new_values', 'metadata', 'request_data', 'response_data',
            'execution_time_ms', 'correlation_id', 'session_id', 'batch_id',
            'passkit_operation', 'passkit_response_code', 'passkit_response_time_ms',
            'passkit_request_headers', 'passkit_response_headers',
            'data_validated', 'validation_errors', 'checksum', 'sensitive_data_masked',
            'workflow_step', 'business_context', 'business_impact_score', 'tags',
            'security_level', 'requires_approval', 'approved_by', 'approved_at',
            'gdpr_relevant', 'pci_relevant',
            'expires_at', 'archived', 'archived_at', 'archive_reason',
        ];

        $attrs = array_intersect_key($data, array_flip($columns));

        $extras = array_diff_key($data, array_flip($columns));
        unset($extras['created_at'], $extras['updated_at']);

        if (!empty($extras)) {
            $attrs['metadata'] = array_merge((array) ($attrs['metadata'] ?? []), $extras);
        }

        $attrs['correlation_id'] = $attrs['correlation_id'] ?? (string) Str::uuid();
        $attrs['source'] = $attrs['source'] ?? 'api';
        $attrs['operation'] = $attrs['operation'] ?? ($data['event_type'] ?? 'unknown');
        $attrs['status'] = $attrs['status'] ?? 'success';
        $attrs['event_type'] = $attrs['event_type'] ?? 'event';
        $attrs['entity_type'] = $attrs['entity_type'] ?? 'system';

        return $attrs;
    }
}
