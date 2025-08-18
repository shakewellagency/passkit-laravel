<?php

use ShakewellAgency\PassKitLaravel\Models\PassKitAuditLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitSecurityLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitPerformanceLog;

describe('PassKitAuditLog Model', function () {
    it('can create an audit log', function () {
        $auditLog = createTestAuditLog([
            'event_type' => 'member_created',
            'entity_type' => 'member',
            'status' => 'success',
            'execution_time_ms' => 150.75,
        ]);

        expect($auditLog)->toBeInstanceOf(PassKitAuditLog::class)
            ->and($auditLog->event_type)->toBe('member_created')
            ->and($auditLog->entity_type)->toBe('member')
            ->and($auditLog->status)->toBe('success')
            ->and($auditLog->execution_time_ms)->toBe(150.75);
    });

    it('has required fillable attributes', function () {
        $auditLog = new PassKitAuditLog();
        $fillable = $auditLog->getFillable();

        expect($fillable)->toContain('account_id')
            ->and($fillable)->toContain('event_type')
            ->and($fillable)->toContain('entity_type')
            ->and($fillable)->toContain('entity_id')
            ->and($fillable)->toContain('user_id')
            ->and($fillable)->toContain('operation')
            ->and($fillable)->toContain('status')
            ->and($fillable)->toContain('execution_time_ms')
            ->and($fillable)->toContain('correlation_id');
    });

    it('casts JSON fields to arrays', function () {
        $auditLog = createTestAuditLog([
            'error_details' => ['code' => 500, 'message' => 'Server error'],
            'old_values' => ['points' => 100],
            'new_values' => ['points' => 150],
            'metadata' => ['source' => 'api', 'version' => '1.0'],
            'request_data' => ['user_id' => 123],
            'response_data' => ['success' => true],
            'tags' => ['critical', 'member'],
        ]);

        expect($auditLog->error_details)->toBeArray()
            ->and($auditLog->old_values)->toBeArray()
            ->and($auditLog->new_values)->toBeArray()
            ->and($auditLog->metadata)->toBeArray()
            ->and($auditLog->request_data)->toBeArray()
            ->and($auditLog->response_data)->toBeArray()
            ->and($auditLog->tags)->toBeArray()
            ->and($auditLog->error_details['code'])->toBe(500)
            ->and($auditLog->tags)->toContain('critical');
    });

    it('casts boolean fields correctly', function () {
        $auditLog = createTestAuditLog([
            'data_validated' => true,
            'sensitive_data_masked' => false,
            'requires_approval' => true,
            'gdpr_relevant' => false,
            'pci_relevant' => true,
            'archived' => false,
        ]);

        expect($auditLog->data_validated)->toBeTrue()
            ->and($auditLog->sensitive_data_masked)->toBeFalse()
            ->and($auditLog->requires_approval)->toBeTrue()
            ->and($auditLog->gdpr_relevant)->toBeFalse()
            ->and($auditLog->pci_relevant)->toBeTrue()
            ->and($auditLog->archived)->toBeFalse();
    });

    it('casts decimal fields correctly', function () {
        $auditLog = createTestAuditLog([
            'execution_time_ms' => 250.123,
            'passkit_response_time_ms' => 150.45,
            'business_impact_score' => 75.5,
        ]);

        expect($auditLog->execution_time_ms)->toBe(250.123)
            ->and($auditLog->passkit_response_time_ms)->toBe(150.45)
            ->and($auditLog->business_impact_score)->toBe(75.5);
    });

    it('casts date fields correctly', function () {
        $auditLog = createTestAuditLog([
            'approved_at' => '2024-01-15 10:30:00',
            'expires_at' => '2024-12-31 23:59:59',
            'archived_at' => '2024-06-15 14:20:00',
        ]);

        expect($auditLog->approved_at)->toBeInstanceOf(Carbon\Carbon::class)
            ->and($auditLog->expires_at)->toBeInstanceOf(Carbon\Carbon::class)
            ->and($auditLog->archived_at)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('has security log relationship', function () {
        $auditLog = createTestAuditLog();
        $securityLog = \ShakewellAgency\PassKitLaravel\Models\PassKitSecurityLog::create([
            'audit_log_id' => $auditLog->id,
            'account_id' => 1,
            'security_event' => 'failed_login',
            'threat_level' => 'medium',
            'source_ip' => '192.168.1.1',
        ]);

        expect($auditLog->securityLog)->toBeInstanceOf(PassKitSecurityLog::class)
            ->and($auditLog->securityLog->id)->toBe($securityLog->id);
    });

    it('has performance log relationship', function () {
        $auditLog = createTestAuditLog();
        $performanceLog = \ShakewellAgency\PassKitLaravel\Models\PassKitPerformanceLog::create([
            'audit_log_id' => $auditLog->id,
            'account_id' => 1,
            'operation_type' => 'member_sync',
            'duration_ms' => 2500.5,
            'performance_threshold_exceeded' => true,
            'performance_grade' => 'D',
            'performance_score' => 25.0,
        ]);

        expect($auditLog->performanceLog)->toBeInstanceOf(PassKitPerformanceLog::class)
            ->and($auditLog->performanceLog->id)->toBe($performanceLog->id);
    });

    it('can scope by account', function () {
        createTestAuditLog(['account_id' => 1]);
        createTestAuditLog(['account_id' => 2]);

        $accountLogs = PassKitAuditLog::byAccount(1)->get();

        expect($accountLogs)->toHaveCount(1)
            ->and($accountLogs->first()->account_id)->toBe(1);
    });

    it('can scope by event type', function () {
        createTestAuditLog(['event_type' => 'member_created']);
        createTestAuditLog(['event_type' => 'points_updated']);

        $memberLogs = PassKitAuditLog::byEventType('member_created')->get();

        expect($memberLogs)->toHaveCount(1)
            ->and($memberLogs->first()->event_type)->toBe('member_created');
    });

    it('can scope by status', function () {
        createTestAuditLog(['status' => 'success']);
        createTestAuditLog(['status' => 'failed']);

        $successLogs = PassKitAuditLog::byStatus('success')->get();

        expect($successLogs)->toHaveCount(1)
            ->and($successLogs->first()->status)->toBe('success');
    });

    it('can scope by security level', function () {
        createTestAuditLog(['security_level' => 'high']);
        createTestAuditLog(['security_level' => 'normal']);

        $highSecurityLogs = PassKitAuditLog::bySecurityLevel('high')->get();

        expect($highSecurityLogs)->toHaveCount(1)
            ->and($highSecurityLogs->first()->security_level)->toBe('high');
    });

    it('can scope high risk logs', function () {
        createTestAuditLog(['security_level' => 'high']);
        createTestAuditLog(['security_level' => 'critical']);
        createTestAuditLog(['security_level' => 'normal']);

        $highRiskLogs = PassKitAuditLog::highRisk()->get();

        expect($highRiskLogs)->toHaveCount(2);
    });

    it('can scope recent logs', function () {
        createTestAuditLog(['created_at' => now()->subDays(3)]);
        createTestAuditLog(['created_at' => now()->subDays(10)]);

        $recentLogs = PassKitAuditLog::recentDays(7)->get();

        expect($recentLogs)->toHaveCount(1);
    });

    it('can scope by correlation id', function () {
        $correlationId = 'test_correlation_123';
        createTestAuditLog(['correlation_id' => $correlationId]);
        createTestAuditLog(['correlation_id' => 'other_correlation']);

        $correlatedLogs = PassKitAuditLog::byCorrelationId($correlationId)->get();

        expect($correlatedLogs)->toHaveCount(1)
            ->and($correlatedLogs->first()->correlation_id)->toBe($correlationId);
    });

    it('can scope failed logs', function () {
        createTestAuditLog(['status' => 'failed']);
        createTestAuditLog(['status' => 'success']);

        $failedLogs = PassKitAuditLog::failed()->get();

        expect($failedLogs)->toHaveCount(1)
            ->and($failedLogs->first()->status)->toBe('failed');
    });

    it('can scope successful logs', function () {
        createTestAuditLog(['status' => 'success']);
        createTestAuditLog(['status' => 'failed']);

        $successfulLogs = PassKitAuditLog::successful()->get();

        expect($successfulLogs)->toHaveCount(1)
            ->and($successfulLogs->first()->status)->toBe('success');
    });

    it('can scope GDPR relevant logs', function () {
        createTestAuditLog(['gdpr_relevant' => true]);
        createTestAuditLog(['gdpr_relevant' => false]);

        $gdprLogs = PassKitAuditLog::gdprRelevant()->get();

        expect($gdprLogs)->toHaveCount(1)
            ->and($gdprLogs->first()->gdpr_relevant)->toBeTrue();
    });

    it('can scope slow operations', function () {
        createTestAuditLog(['execution_time_ms' => 6000]);
        createTestAuditLog(['execution_time_ms' => 2000]);

        $slowLogs = PassKitAuditLog::slowOperations(5000)->get();

        expect($slowLogs)->toHaveCount(1)
            ->and($slowLogs->first()->execution_time_ms)->toBe(6000.0);
    });

    it('has is_high_risk accessor', function () {
        $highRiskLog = createTestAuditLog(['security_level' => 'high']);
        $normalLog = createTestAuditLog(['security_level' => 'normal']);

        expect($highRiskLog->is_high_risk)->toBeTrue()
            ->and($normalLog->is_high_risk)->toBeFalse();
    });

    it('has is_expired accessor', function () {
        $expiredLog = createTestAuditLog(['expires_at' => now()->subDays(1)]);
        $validLog = createTestAuditLog(['expires_at' => now()->addDays(1)]);

        expect($expiredLog->is_expired)->toBeTrue()
            ->and($validLog->is_expired)->toBeFalse();
    });

    it('has is_approved accessor', function () {
        $approvedLog = createTestAuditLog([
            'requires_approval' => true,
            'approved_at' => now(),
        ]);
        $pendingLog = createTestAuditLog([
            'requires_approval' => true,
            'approved_at' => null,
        ]);

        expect($approvedLog->is_approved)->toBeTrue()
            ->and($pendingLog->is_approved)->toBeFalse();
    });

    it('has formatted_execution_time accessor', function () {
        $fastLog = createTestAuditLog(['execution_time_ms' => 500.25]);
        $slowLog = createTestAuditLog(['execution_time_ms' => 2500.75]);

        expect($fastLog->formatted_execution_time)->toBe('500.25ms')
            ->and($slowLog->formatted_execution_time)->toBe('2.50s');
    });

    it('has risk_score accessor', function () {
        $highRiskLog = createTestAuditLog([
            'security_level' => 'critical',
            'status' => 'failed',
            'business_impact_score' => 80,
            'gdpr_relevant' => true,
        ]);

        $riskScore = $highRiskLog->risk_score;

        expect($riskScore)->toBeFloat()
            ->and($riskScore)->toBeGreaterThan(50);
    });

    it('can approve log', function () {
        $log = createTestAuditLog(['requires_approval' => true]);

        $log->approve(1, 'Approved for processing');

        expect($log->approved_by)->toBe(1)
            ->and($log->approved_at)->not->toBeNull()
            ->and($log->metadata['approval_notes'])->toBe('Approved for processing');
    });

    it('can archive log', function () {
        $log = createTestAuditLog(['archived' => false]);

        $log->archive('Retention policy');

        expect($log->archived)->toBeTrue()
            ->and($log->archived_at)->not->toBeNull()
            ->and($log->archive_reason)->toBe('Retention policy');
    });

    it('can add and remove tags', function () {
        $log = createTestAuditLog(['tags' => ['existing']]);

        $log->addTag('new_tag');
        expect($log->tags)->toContain('new_tag')
            ->and($log->tags)->toContain('existing');

        $log->removeTag('existing');
        expect($log->tags)->not->toContain('existing')
            ->and($log->tags)->toContain('new_tag');
    });

    it('can check if has tag', function () {
        $log = createTestAuditLog(['tags' => ['critical', 'member']]);

        expect($log->hasTag('critical'))->toBeTrue()
            ->and($log->hasTag('nonexistent'))->toBeFalse();
    });

    it('can get related logs by correlation id', function () {
        $correlationId = 'test_correlation_batch';
        
        $log1 = createTestAuditLog(['correlation_id' => $correlationId]);
        createTestAuditLog(['correlation_id' => $correlationId]);
        createTestAuditLog(['correlation_id' => 'different_correlation']);

        $relatedLogs = $log1->getRelatedLogs();

        expect($relatedLogs)->toHaveCount(1); // Excludes the current log
    });

    it('can get event type stats', function () {
        createTestAuditLog([
            'account_id' => 1,
            'event_type' => 'member_created',
            'execution_time_ms' => 100,
        ]);
        createTestAuditLog([
            'account_id' => 1,
            'event_type' => 'member_created',
            'execution_time_ms' => 200,
        ]);
        createTestAuditLog([
            'account_id' => 1,
            'event_type' => 'points_updated',
            'execution_time_ms' => 150,
        ]);

        $stats = PassKitAuditLog::getEventTypeStats(1, 7);

        expect($stats)->toHaveCount(2)
            ->and($stats[0]['event_type'])->toBe('member_created')
            ->and($stats[0]['count'])->toBe(2);
    });

    it('can get security summary', function () {
        createTestAuditLog([
            'account_id' => 1,
            'event_type' => 'security_event',
            'security_level' => 'high',
            'status' => 'failed',
            'gdpr_relevant' => true,
        ]);
        createTestAuditLog([
            'account_id' => 1,
            'security_level' => 'normal',
            'status' => 'success',
        ]);

        $summary = PassKitAuditLog::getSecuritySummary(1, 7);

        expect($summary)->toHaveKeys([
            'total_events',
            'high_risk_events',
            'failed_events',
            'security_events',
            'gdpr_events',
        ])
            ->and($summary['total_events'])->toBe(2)
            ->and($summary['high_risk_events'])->toBe(1)
            ->and($summary['failed_events'])->toBe(1);
    });

    it('can get performance summary', function () {
        createTestAuditLog([
            'account_id' => 1,
            'execution_time_ms' => 6000,
            'status' => 'success',
        ]);
        createTestAuditLog([
            'account_id' => 1,
            'execution_time_ms' => 2000,
            'status' => 'success',
        ]);

        $summary = PassKitAuditLog::getPerformanceSummary(1, 7);

        expect($summary)->toHaveKeys([
            'avg_execution_time',
            'max_execution_time',
            'slow_operations',
            'total_operations',
            'success_rate',
        ])
            ->and($summary['avg_execution_time'])->toBe(4000.0)
            ->and($summary['max_execution_time'])->toBe(6000.0)
            ->and($summary['slow_operations'])->toBe(1)
            ->and($summary['success_rate'])->toBe(100.0);
    });
});