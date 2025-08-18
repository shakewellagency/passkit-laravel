<?php

namespace ShakewellAgency\PassKitLaravel\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use ShakewellAgency\PassKitLaravel\Models\PassKitAuditLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitSecurityLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitPerformanceLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitDataChange;
use ShakewellAgency\PassKitLaravel\Models\PassKitComplianceLog;

class PassKitAuditService
{
    protected array $currentOperation = [];
    protected ?string $correlationId = null;
    protected ?string $batchId = null;
    protected float $operationStartTime;

    public function __construct()
    {
        $this->correlationId = Str::uuid()->toString();
        $this->operationStartTime = microtime(true);
    }

    /**
     * Start a new audit operation
     */
    public function startOperation(string $operation, array $context = []): string
    {
        $this->correlationId = Str::uuid()->toString();
        $this->operationStartTime = microtime(true);
        
        $this->currentOperation = array_merge([
            'operation' => $operation,
            'correlation_id' => $this->correlationId,
            'started_at' => now(),
            'start_time' => $this->operationStartTime,
        ], $context);

        return $this->correlationId;
    }

    /**
     * Log a general audit event
     */
    public function log(array $data): PassKitAuditLog
    {
        $executionTime = (microtime(true) - $this->operationStartTime) * 1000;

        $auditData = array_merge([
            'correlation_id' => $this->correlationId,
            'batch_id' => $this->batchId,
            'execution_time_ms' => $executionTime,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'source' => $this->determineSource(),
            'user_id' => Auth::id(),
            'user_type' => $this->determineUserType(),
            'session_id' => session()->getId(),
            'data_validated' => true,
            'sensitive_data_masked' => $this->containsSensitiveData($data),
            'security_level' => $this->determineSecurityLevel($data),
            'business_impact_score' => $this->calculateBusinessImpact($data),
            'checksum' => $this->generateChecksum($data),
        ], $data);

        // Mask sensitive data
        if ($auditData['sensitive_data_masked']) {
            $auditData = $this->maskSensitiveData($auditData);
        }

        $auditLog = PassKitAuditLog::create($auditData);

        // Create related logs based on event type
        $this->createRelatedLogs($auditLog, $data);

        return $auditLog;
    }

    /**
     * Log a successful operation
     */
    public function logSuccess(string $eventType, string $entityType, array $context = []): PassKitAuditLog
    {
        return $this->log(array_merge([
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'status' => 'success',
            'operation' => $this->currentOperation['operation'] ?? $eventType,
        ], $context));
    }

    /**
     * Log a failed operation
     */
    public function logFailure(string $eventType, string $entityType, string $errorMessage, array $context = []): PassKitAuditLog
    {
        return $this->log(array_merge([
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'status' => 'failed',
            'operation' => $this->currentOperation['operation'] ?? $eventType,
            'error_message' => $errorMessage,
            'error_details' => $context['error_details'] ?? null,
        ], $context));
    }

    /**
     * Log data changes for sensitive operations
     */
    public function logDataChange(string $tableName, string $columnName, $oldValue, $newValue, array $context = []): PassKitAuditLog
    {
        $auditLog = $this->log(array_merge([
            'event_type' => 'data_change',
            'entity_type' => $tableName,
            'operation' => 'update',
            'status' => 'success',
            'description' => "Data changed in {$tableName}.{$columnName}",
        ], $context));

        // Create detailed change record
        PassKitDataChange::create([
            'audit_log_id' => $auditLog->id,
            'account_id' => $context['account_id'] ?? 0,
            'table_name' => $tableName,
            'column_name' => $columnName,
            'old_value' => $this->serializeValue($oldValue),
            'new_value' => $this->serializeValue($newValue),
            'change_type' => 'update',
            'data_classification' => $this->classifyData($columnName, $newValue),
            'pii_data' => $this->isPiiData($columnName, $newValue),
            'financial_data' => $this->isFinancialData($columnName, $newValue),
            'sensitive_data' => $this->isSensitiveData($columnName, $newValue),
            'impact_level' => $this->assessChangeImpact($tableName, $columnName, $oldValue, $newValue),
            'requires_notification' => $this->requiresNotification($tableName, $columnName),
            'requires_approval' => $this->requiresApproval($tableName, $columnName),
            'rollback_available' => true,
            'rollback_expires_at' => now()->addDays(30),
        ]);

        return $auditLog;
    }

    /**
     * Log security events
     */
    public function logSecurityEvent(string $securityEvent, string $threatLevel, array $context = []): PassKitAuditLog
    {
        $auditLog = $this->log(array_merge([
            'event_type' => 'security_event',
            'entity_type' => 'security',
            'operation' => $securityEvent,
            'status' => 'warning',
            'security_level' => 'high',
            'description' => "Security event: {$securityEvent}",
        ], $context));

        // Create detailed security log
        PassKitSecurityLog::create([
            'audit_log_id' => $auditLog->id,
            'account_id' => $context['account_id'] ?? 0,
            'security_event' => $securityEvent,
            'threat_level' => $threatLevel,
            'source_ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'attack_pattern' => $context['attack_pattern'] ?? null,
            'automated_detection' => $context['automated_detection'] ?? false,
            'detection_rule' => $context['detection_rule'] ?? null,
            'confidence_score' => $context['confidence_score'] ?? null,
            'response_action' => $context['response_action'] ?? 'logged',
            'automatic_response' => $context['automatic_response'] ?? false,
            'requires_investigation' => $threatLevel === 'critical' || $threatLevel === 'high',
            'regulatory_reportable' => $context['regulatory_reportable'] ?? false,
        ]);

        return $auditLog;
    }

    /**
     * Log performance metrics
     */
    public function logPerformance(string $operationType, float $durationMs, array $metrics = []): PassKitAuditLog
    {
        $auditLog = $this->log([
            'event_type' => 'performance_metric',
            'entity_type' => 'performance',
            'operation' => $operationType,
            'status' => 'success',
            'description' => "Performance metrics for {$operationType}",
        ]);

        // Create detailed performance log
        PassKitPerformanceLog::create(array_merge([
            'audit_log_id' => $auditLog->id,
            'account_id' => $metrics['account_id'] ?? 0,
            'operation_type' => $operationType,
            'duration_ms' => $durationMs,
            'performance_threshold_exceeded' => $durationMs > ($metrics['threshold_ms'] ?? 5000),
            'performance_grade' => $this->calculatePerformanceGrade($durationMs, $metrics),
            'performance_score' => $this->calculatePerformanceScore($durationMs, $metrics),
        ], $metrics));

        return $auditLog;
    }

    /**
     * Log compliance events
     */
    public function logCompliance(string $regulation, string $requirement, string $status, array $context = []): PassKitAuditLog
    {
        $auditLog = $this->log(array_merge([
            'event_type' => 'compliance_check',
            'entity_type' => 'compliance',
            'operation' => $regulation,
            'status' => $status === 'compliant' ? 'success' : 'warning',
            'description' => "Compliance check for {$regulation}: {$requirement}",
        ], $context));

        // Create detailed compliance log
        PassKitComplianceLog::create([
            'audit_log_id' => $auditLog->id,
            'account_id' => $context['account_id'] ?? 0,
            'regulation' => $regulation,
            'compliance_requirement' => $requirement,
            'compliance_status' => $status,
            'compliance_score' => $context['compliance_score'] ?? null,
            'risk_level' => $context['risk_level'] ?? 'medium',
            'requires_remediation' => $status !== 'compliant',
            'remediation_deadline' => $status !== 'compliant' ? now()->addDays(30) : null,
        ]);

        return $auditLog;
    }

    /**
     * Set batch ID for related operations
     */
    public function setBatchId(string $batchId): void
    {
        $this->batchId = $batchId;
    }

    /**
     * Log PassKit API operations
     */
    public function logPassKitOperation(string $operation, array $request, array $response, float $responseTime): PassKitAuditLog
    {
        return $this->log([
            'event_type' => 'passkit_api_call',
            'entity_type' => 'api',
            'operation' => $operation,
            'status' => isset($response['error']) ? 'failed' : 'success',
            'description' => "PassKit API call: {$operation}",
            'passkit_operation' => $operation,
            'passkit_response_code' => $response['status_code'] ?? 200,
            'passkit_response_time_ms' => $responseTime,
            'request_data' => $this->sanitizeApiData($request),
            'response_data' => $this->sanitizeApiData($response),
            'error_message' => $response['error'] ?? null,
        ]);
    }

    /**
     * Get audit statistics
     */
    public function getAuditStatistics(int $accountId, int $days = 7): array
    {
        $startDate = now()->subDays($days);

        return [
            'total_events' => PassKitAuditLog::where('account_id', $accountId)
                ->where('created_at', '>=', $startDate)
                ->count(),
            
            'events_by_type' => PassKitAuditLog::where('account_id', $accountId)
                ->where('created_at', '>=', $startDate)
                ->groupBy('event_type')
                ->selectRaw('event_type, COUNT(*) as count')
                ->pluck('count', 'event_type'),
            
            'success_rate' => PassKitAuditLog::where('account_id', $accountId)
                ->where('created_at', '>=', $startDate)
                ->where('status', 'success')
                ->count() / max(1, PassKitAuditLog::where('account_id', $accountId)
                ->where('created_at', '>=', $startDate)
                ->count()) * 100,
            
            'security_events' => PassKitSecurityLog::where('account_id', $accountId)
                ->where('created_at', '>=', $startDate)
                ->count(),
            
            'high_risk_events' => PassKitSecurityLog::where('account_id', $accountId)
                ->where('created_at', '>=', $startDate)
                ->whereIn('threat_level', ['high', 'critical'])
                ->count(),
            
            'performance_issues' => PassKitPerformanceLog::where('account_id', $accountId)
                ->where('created_at', '>=', $startDate)
                ->where('performance_threshold_exceeded', true)
                ->count(),
            
            'compliance_violations' => PassKitComplianceLog::where('account_id', $accountId)
                ->where('created_at', '>=', $startDate)
                ->where('compliance_status', 'non_compliant')
                ->count(),
        ];
    }

    /**
     * Clean up old audit logs based on retention policy
     */
    public function cleanupAuditLogs(int $retentionDays = 90): int
    {
        $cutoffDate = now()->subDays($retentionDays);
        
        return PassKitAuditLog::where('created_at', '<', $cutoffDate)
            ->where('archived', false)
            ->delete();
    }

    // Protected helper methods

    protected function determineSource(): string
    {
        if (app()->runningInConsole()) {
            return 'command';
        }
        
        if (request()->is('api/*')) {
            return 'api';
        }
        
        if (request()->header('X-Webhook-Source')) {
            return 'webhook';
        }
        
        return 'web';
    }

    protected function determineUserType(): ?string
    {
        if (!Auth::check()) {
            return 'anonymous';
        }
        
        $user = Auth::user();
        
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return 'admin';
        }
        
        return 'user';
    }

    protected function determineSecurityLevel(array $data): string
    {
        if (isset($data['passkit_operation']) || isset($data['financial_data'])) {
            return 'high';
        }
        
        if (isset($data['pii_data']) && $data['pii_data']) {
            return 'high';
        }
        
        if (in_array($data['event_type'] ?? '', ['security_event', 'compliance_check'])) {
            return 'critical';
        }
        
        return 'normal';
    }

    protected function calculateBusinessImpact(array $data): float
    {
        $impact = 10.0; // Base impact
        
        // Increase impact for critical operations
        if (in_array($data['event_type'] ?? '', ['member_delete', 'program_delete', 'security_event'])) {
            $impact += 30.0;
        }
        
        // Increase impact for financial operations
        if (isset($data['financial_data']) && $data['financial_data']) {
            $impact += 20.0;
        }
        
        // Increase impact for failed operations
        if (($data['status'] ?? '') === 'failed') {
            $impact += 15.0;
        }
        
        return min(100.0, $impact);
    }

    protected function containsSensitiveData(array $data): bool
    {
        $sensitiveFields = ['password', 'email', 'phone', 'ssn', 'credit_card', 'api_key', 'token'];
        
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                foreach ($sensitiveFields as $sensitiveField) {
                    if (stripos($key, $sensitiveField) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    protected function maskSensitiveData(array $data): array
    {
        $sensitiveFields = ['password', 'email', 'phone', 'ssn', 'credit_card', 'api_key', 'token'];
        
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                foreach ($sensitiveFields as $sensitiveField) {
                    if (stripos($key, $sensitiveField) !== false) {
                        $data[$key] = $this->maskValue($value);
                    }
                }
            }
        }
        
        return $data;
    }

    protected function maskValue($value): string
    {
        if (!is_string($value)) {
            return '[MASKED]';
        }
        
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }
        
        return substr($value, 0, 2) . str_repeat('*', strlen($value) - 4) . substr($value, -2);
    }

    protected function generateChecksum(array $data): string
    {
        $sanitizedData = $data;
        unset($sanitizedData['created_at'], $sanitizedData['updated_at'], $sanitizedData['id']);
        
        return hash('sha256', json_encode($sanitizedData, JSON_SORT_KEYS));
    }

    protected function createRelatedLogs(PassKitAuditLog $auditLog, array $data): void
    {
        // Auto-create performance log for long-running operations
        if (($data['execution_time_ms'] ?? 0) > 1000) {
            PassKitPerformanceLog::create([
                'audit_log_id' => $auditLog->id,
                'account_id' => $data['account_id'] ?? 0,
                'operation_type' => $data['operation'] ?? 'unknown',
                'duration_ms' => $data['execution_time_ms'],
                'performance_threshold_exceeded' => true,
                'performance_grade' => 'D',
                'performance_score' => 25.0,
            ]);
        }
    }

    protected function serializeValue($value): string
    {
        if (is_null($value)) {
            return '[NULL]';
        }
        
        if (is_bool($value)) {
            return $value ? '[TRUE]' : '[FALSE]';
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }

    protected function classifyData(string $columnName, $value): string
    {
        $confidentialColumns = ['password', 'token', 'secret', 'key'];
        $restrictedColumns = ['ssn', 'credit_card', 'bank_account'];
        $internalColumns = ['email', 'phone', 'address'];
        
        $columnLower = strtolower($columnName);
        
        foreach ($restrictedColumns as $restricted) {
            if (stripos($columnLower, $restricted) !== false) {
                return 'restricted';
            }
        }
        
        foreach ($confidentialColumns as $confidential) {
            if (stripos($columnLower, $confidential) !== false) {
                return 'confidential';
            }
        }
        
        foreach ($internalColumns as $internal) {
            if (stripos($columnLower, $internal) !== false) {
                return 'internal';
            }
        }
        
        return 'public';
    }

    protected function isPiiData(string $columnName, $value): bool
    {
        $piiColumns = ['email', 'phone', 'first_name', 'last_name', 'address', 'ssn', 'date_of_birth'];
        
        foreach ($piiColumns as $piiColumn) {
            if (stripos($columnName, $piiColumn) !== false) {
                return true;
            }
        }
        
        return false;
    }

    protected function isFinancialData(string $columnName, $value): bool
    {
        $financialColumns = ['credit_card', 'bank_account', 'points', 'balance', 'amount', 'payment'];
        
        foreach ($financialColumns as $financialColumn) {
            if (stripos($columnName, $financialColumn) !== false) {
                return true;
            }
        }
        
        return false;
    }

    protected function isSensitiveData(string $columnName, $value): bool
    {
        return $this->isPiiData($columnName, $value) || 
               $this->isFinancialData($columnName, $value) ||
               in_array($this->classifyData($columnName, $value), ['confidential', 'restricted']);
    }

    protected function assessChangeImpact(string $tableName, string $columnName, $oldValue, $newValue): string
    {
        // Critical impact for certain tables/columns
        $criticalTables = ['passkit_members', 'passkit_transactions'];
        $criticalColumns = ['points_balance', 'status', 'passkit_id'];
        
        if (in_array($tableName, $criticalTables) && in_array($columnName, $criticalColumns)) {
            return 'critical';
        }
        
        // High impact for financial or PII data
        if ($this->isFinancialData($columnName, $newValue) || $this->isPiiData($columnName, $newValue)) {
            return 'high';
        }
        
        return 'medium';
    }

    protected function requiresNotification(string $tableName, string $columnName): bool
    {
        $notificationTables = ['passkit_members', 'passkit_transactions', 'pass_kit_programs'];
        $notificationColumns = ['status', 'points_balance', 'tier_id'];
        
        return in_array($tableName, $notificationTables) && in_array($columnName, $notificationColumns);
    }

    protected function requiresApproval(string $tableName, string $columnName): bool
    {
        $approvalColumns = ['points_balance', 'status', 'tier_id'];
        
        return in_array($columnName, $approvalColumns);
    }

    protected function calculatePerformanceGrade(float $durationMs, array $metrics): string
    {
        $threshold = $metrics['threshold_ms'] ?? 1000;
        
        if ($durationMs <= $threshold * 0.5) return 'A';
        if ($durationMs <= $threshold * 0.75) return 'B';
        if ($durationMs <= $threshold) return 'C';
        if ($durationMs <= $threshold * 2) return 'D';
        
        return 'F';
    }

    protected function calculatePerformanceScore(float $durationMs, array $metrics): float
    {
        $threshold = $metrics['threshold_ms'] ?? 1000;
        $ratio = $durationMs / $threshold;
        
        if ($ratio <= 0.5) return 100.0;
        if ($ratio <= 1.0) return max(50.0, 100.0 - ($ratio - 0.5) * 100);
        
        return max(0.0, 50.0 - ($ratio - 1.0) * 25);
    }

    protected function sanitizeApiData(array $data): array
    {
        // Remove sensitive fields from API logging
        $sensitiveKeys = ['password', 'token', 'key', 'secret', 'credential'];
        
        foreach ($data as $key => $value) {
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $data[$key] = '[REDACTED]';
                }
            }
            
            if (is_array($value)) {
                $data[$key] = $this->sanitizeApiData($value);
            }
        }
        
        return $data;
    }
}