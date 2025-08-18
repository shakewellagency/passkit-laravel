<?php

use ShakewellAgency\PassKitLaravel\Services\PassKitAuditService;
use ShakewellAgency\PassKitLaravel\Models\PassKitAuditLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitSecurityLog;
use ShakewellAgency\PassKitLaravel\Models\PassKitPerformanceLog;

describe('PassKitAuditService', function () {
    beforeEach(function () {
        $this->auditService = app(PassKitAuditService::class);
    });

    it('can instantiate the audit service', function () {
        expect($this->auditService)->toBeInstanceOf(PassKitAuditService::class);
    });

    describe('Basic Audit Logging', function () {
        it('can log an event', function () {
            $eventData = [
                'event_type' => 'member_created',
                'entity_type' => 'member',
                'entity_id' => 123,
                'account_id' => 1,
                'user_id' => 1,
                'operation' => 'create',
                'status' => 'success',
                'description' => 'Member successfully created',
            ];

            $log = $this->auditService->log($eventData);

            expect($log)->toBeInstanceOf(PassKitAuditLog::class)
                ->and($log->event_type)->toBe('member_created')
                ->and($log->entity_type)->toBe('member')
                ->and($log->status)->toBe('success');
        });

        it('auto-generates correlation_id if not provided', function () {
            $eventData = [
                'event_type' => 'test_event',
                'account_id' => 1,
            ];

            $log = $this->auditService->log($eventData);

            expect($log->correlation_id)->not->toBeNull()
                ->and($log->correlation_id)->toMatch('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/');
        });

        it('can log with execution time tracking', function () {
            $startTime = microtime(true);
            
            // Simulate some processing time
            usleep(10000); // 10ms
            
            $executionTime = (microtime(true) - $startTime) * 1000;

            $eventData = [
                'event_type' => 'api_call',
                'account_id' => 1,
                'execution_time_ms' => $executionTime,
            ];

            $log = $this->auditService->log($eventData);

            expect($log->execution_time_ms)->toBeGreaterThan(5)
                ->and($log->execution_time_ms)->toBeLessThan(100);
        });

        it('can log with metadata', function () {
            $eventData = [
                'event_type' => 'api_request',
                'account_id' => 1,
                'metadata' => [
                    'endpoint' => '/api/members',
                    'method' => 'POST',
                    'ip_address' => '192.168.1.1',
                    'user_agent' => 'Test Agent',
                ],
                'request_data' => [
                    'email' => 'test@example.com',
                    'points' => 100,
                ],
                'response_data' => [
                    'member_id' => 123,
                    'success' => true,
                ],
            ];

            $log = $this->auditService->log($eventData);

            expect($log->metadata)->toBeArray()
                ->and($log->metadata['endpoint'])->toBe('/api/members')
                ->and($log->request_data['email'])->toBe('test@example.com')
                ->and($log->response_data['success'])->toBeTrue();
        });
    });

    describe('Security Logging', function () {
        it('can log security events', function () {
            $securityData = [
                'event_type' => 'security_violation',
                'account_id' => 1,
                'security_level' => 'high',
                'security_event' => 'failed_authentication',
                'threat_level' => 'medium',
                'source_ip' => '192.168.1.100',
                'user_agent' => 'Suspicious Bot',
                'risk_indicators' => ['multiple_failed_attempts', 'unusual_location'],
                'mitigation_actions' => ['account_locked', 'admin_notified'],
            ];

            $log = $this->auditService->logSecurityEvent($securityData);

            expect($log)->toBeInstanceOf(PassKitAuditLog::class)
                ->and($log->security_level)->toBe('high')
                ->and($log->securityLog)->toBeInstanceOf(PassKitSecurityLog::class)
                ->and($log->securityLog->security_event)->toBe('failed_authentication')
                ->and($log->securityLog->threat_level)->toBe('medium');
        });

        it('can detect and log suspicious patterns', function () {
            // Create multiple failed login attempts
            for ($i = 0; $i < 5; $i++) {
                $this->auditService->logSecurityEvent([
                    'event_type' => 'login_failed',
                    'account_id' => 1,
                    'security_event' => 'failed_authentication',
                    'source_ip' => '192.168.1.100',
                    'created_at' => now()->subMinutes(10 - $i),
                ]);
            }

            $suspiciousActivity = $this->auditService->detectSuspiciousPatterns(1);

            expect($suspiciousActivity)->toBeArray()
                ->and($suspiciousActivity)->toHaveKey('failed_logins')
                ->and($suspiciousActivity['failed_logins']['count'])->toBe(5);
        });

        it('can log GDPR relevant events', function () {
            $gdprData = [
                'event_type' => 'data_access',
                'account_id' => 1,
                'user_id' => 123,
                'gdpr_relevant' => true,
                'data_categories' => ['personal_info', 'transaction_history'],
                'legal_basis' => 'consent',
                'retention_period' => '7 years',
            ];

            $log = $this->auditService->log($gdprData);

            expect($log->gdpr_relevant)->toBeTrue()
                ->and($log->metadata['data_categories'])->toContain('personal_info')
                ->and($log->metadata['legal_basis'])->toBe('consent');
        });

        it('can log PCI compliance events', function () {
            $pciData = [
                'event_type' => 'payment_processing',
                'account_id' => 1,
                'pci_relevant' => true,
                'payment_method' => 'credit_card',
                'card_type' => 'visa',
                'last_four_digits' => '1234',
                'pci_compliance_level' => 'level_1',
            ];

            $log = $this->auditService->log($pciData);

            expect($log->pci_relevant)->toBeTrue()
                ->and($log->metadata['payment_method'])->toBe('credit_card')
                ->and($log->metadata['pci_compliance_level'])->toBe('level_1');
        });
    });

    describe('Performance Logging', function () {
        it('can log performance metrics', function () {
            $performanceData = [
                'event_type' => 'api_performance',
                'account_id' => 1,
                'operation_type' => 'member_sync',
                'execution_time_ms' => 2500.75,
                'passkit_response_time_ms' => 1800.25,
                'memory_usage_mb' => 45.5,
                'cpu_usage_percent' => 78.2,
                'performance_threshold_exceeded' => true,
                'performance_grade' => 'C',
                'performance_score' => 65.5,
            ];

            $log = $this->auditService->logPerformanceEvent($performanceData);

            expect($log)->toBeInstanceOf(PassKitAuditLog::class)
                ->and($log->execution_time_ms)->toBe(2500.75)
                ->and($log->performanceLog)->toBeInstanceOf(PassKitPerformanceLog::class)
                ->and($log->performanceLog->operation_type)->toBe('member_sync')
                ->and($log->performanceLog->performance_threshold_exceeded)->toBeTrue();
        });

        it('can track slow operations', function () {
            $this->auditService->logPerformanceEvent([
                'event_type' => 'slow_operation',
                'account_id' => 1,
                'operation_type' => 'bulk_sync',
                'execution_time_ms' => 8500,
                'performance_threshold_exceeded' => true,
                'performance_grade' => 'F',
            ]);

            $slowOps = $this->auditService->getSlowOperations(1, 5000);

            expect($slowOps)->toHaveCount(1)
                ->and($slowOps->first()->execution_time_ms)->toBe(8500.0);
        });

        it('can generate performance summary', function () {
            // Create performance logs
            $this->auditService->logPerformanceEvent([
                'account_id' => 1,
                'operation_type' => 'api_call',
                'execution_time_ms' => 150,
                'performance_grade' => 'A',
            ]);
            
            $this->auditService->logPerformanceEvent([
                'account_id' => 1,
                'operation_type' => 'api_call',
                'execution_time_ms' => 300,
                'performance_grade' => 'B',
            ]);

            $summary = $this->auditService->getPerformanceSummary(1, 7);

            expect($summary)->toHaveKeys([
                'avg_execution_time',
                'max_execution_time',
                'slow_operations',
                'performance_distribution',
            ])
                ->and($summary['avg_execution_time'])->toBe(225.0)
                ->and($summary['max_execution_time'])->toBe(300.0);
        });
    });

    describe('Change Tracking', function () {
        it('can log entity changes', function () {
            $oldValues = [
                'email' => 'old@example.com',
                'points' => 100,
                'status' => 'inactive',
            ];

            $newValues = [
                'email' => 'new@example.com',
                'points' => 150,
                'status' => 'active',
            ];

            $changeData = [
                'event_type' => 'member_updated',
                'entity_type' => 'member',
                'entity_id' => 123,
                'account_id' => 1,
                'user_id' => 1,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'operation' => 'update',
                'status' => 'success',
            ];

            $log = $this->auditService->logChange($changeData);

            expect($log->old_values)->toBeArray()
                ->and($log->new_values)->toBeArray()
                ->and($log->old_values['email'])->toBe('old@example.com')
                ->and($log->new_values['email'])->toBe('new@example.com')
                ->and($log->old_values['points'])->toBe(100)
                ->and($log->new_values['points'])->toBe(150);
        });

        it('can detect significant changes', function () {
            $changeData = [
                'event_type' => 'member_updated',
                'entity_type' => 'member',
                'entity_id' => 123,
                'account_id' => 1,
                'old_values' => ['points' => 100],
                'new_values' => ['points' => 10000], // Significant increase
            ];

            $log = $this->auditService->logChange($changeData);
            $significance = $this->auditService->assessChangeSignificance($log);

            expect($significance)->toHaveKey('is_significant')
                ->and($significance['is_significant'])->toBeTrue()
                ->and($significance['significance_reasons'])->toContain('large_points_change');
        });

        it('can track change history for entity', function () {
            $entityId = 123;
            
            // Log multiple changes
            for ($i = 1; $i <= 3; $i++) {
                $this->auditService->logChange([
                    'event_type' => 'member_updated',
                    'entity_type' => 'member',
                    'entity_id' => $entityId,
                    'account_id' => 1,
                    'old_values' => ['points' => $i * 50],
                    'new_values' => ['points' => ($i + 1) * 50],
                ]);
            }

            $history = $this->auditService->getEntityChangeHistory('member', $entityId);

            expect($history)->toHaveCount(3)
                ->and($history->first()->new_values['points'])->toBe(200);
        });
    });

    describe('Compliance and Reporting', function () {
        it('can generate compliance report', function () {
            // Create various audit logs
            $this->auditService->log([
                'event_type' => 'data_access',
                'account_id' => 1,
                'gdpr_relevant' => true,
                'status' => 'success',
            ]);

            $this->auditService->logSecurityEvent([
                'event_type' => 'security_scan',
                'account_id' => 1,
                'security_level' => 'normal',
            ]);

            $report = $this->auditService->generateComplianceReport(1, [
                'period_days' => 30,
                'include_gdpr' => true,
                'include_security' => true,
            ]);

            expect($report)->toHaveKeys([
                'period',
                'total_events',
                'gdpr_events',
                'security_events',
                'compliance_score',
            ])
                ->and($report['gdpr_events'])->toBe(1)
                ->and($report['security_events'])->toBe(1);
        });

        it('can generate audit trail for entity', function () {
            $entityId = 456;
            
            $this->auditService->log([
                'event_type' => 'member_created',
                'entity_type' => 'member',
                'entity_id' => $entityId,
                'account_id' => 1,
            ]);

            $this->auditService->logChange([
                'event_type' => 'member_updated',
                'entity_type' => 'member',
                'entity_id' => $entityId,
                'account_id' => 1,
                'old_values' => ['status' => 'pending'],
                'new_values' => ['status' => 'active'],
            ]);

            $trail = $this->auditService->generateEntityAuditTrail('member', $entityId);

            expect($trail)->toHaveCount(2)
                ->and($trail[0]['event_type'])->toBe('member_created')
                ->and($trail[1]['event_type'])->toBe('member_updated');
        });

        it('can export audit logs', function () {
            createTestAuditLog(['account_id' => 1, 'event_type' => 'test_event_1']);
            createTestAuditLog(['account_id' => 1, 'event_type' => 'test_event_2']);

            $export = $this->auditService->exportAuditLogs(1, [
                'format' => 'csv',
                'date_from' => now()->subDays(1),
                'date_to' => now(),
            ]);

            expect($export)->toHaveKeys(['filename', 'content', 'mime_type'])
                ->and($export['mime_type'])->toBe('text/csv')
                ->and($export['content'])->toContain('test_event_1');
        });
    });

    describe('Search and Analytics', function () {
        it('can search audit logs', function () {
            createTestAuditLog([
                'account_id' => 1,
                'event_type' => 'member_created',
                'description' => 'New member John Doe',
            ]);
            
            createTestAuditLog([
                'account_id' => 1,
                'event_type' => 'points_updated',
                'description' => 'Points awarded to Jane Smith',
            ]);

            $results = $this->auditService->searchLogs(1, [
                'query' => 'John Doe',
                'event_types' => ['member_created'],
            ]);

            expect($results)->toHaveCount(1)
                ->and($results->first()->description)->toContain('John Doe');
        });

        it('can get event statistics', function () {
            createTestAuditLog(['account_id' => 1, 'event_type' => 'member_created']);
            createTestAuditLog(['account_id' => 1, 'event_type' => 'member_created']);
            createTestAuditLog(['account_id' => 1, 'event_type' => 'points_updated']);

            $stats = $this->auditService->getEventStatistics(1, 7);

            expect($stats)->toHaveCount(2)
                ->and($stats[0]['event_type'])->toBe('member_created')
                ->and($stats[0]['count'])->toBe(2);
        });

        it('can analyze user activity patterns', function () {
            $userId = 123;
            
            for ($i = 0; $i < 10; $i++) {
                createTestAuditLog([
                    'account_id' => 1,
                    'user_id' => $userId,
                    'event_type' => 'api_request',
                    'created_at' => now()->subHours($i),
                ]);
            }

            $patterns = $this->auditService->analyzeUserActivity($userId, 1);

            expect($patterns)->toHaveKeys([
                'total_events',
                'events_by_hour',
                'most_active_hour',
                'activity_trend',
            ])
                ->and($patterns['total_events'])->toBe(10);
        });

        it('can detect anomalies', function () {
            // Create normal activity
            for ($i = 0; $i < 50; $i++) {
                createTestAuditLog([
                    'account_id' => 1,
                    'event_type' => 'api_request',
                    'execution_time_ms' => 100 + ($i % 20), // Normal range
                ]);
            }

            // Create anomalous activity
            createTestAuditLog([
                'account_id' => 1,
                'event_type' => 'api_request',
                'execution_time_ms' => 5000, // Anomalously slow
            ]);

            $anomalies = $this->auditService->detectAnomalies(1);

            expect($anomalies)->not->toBeEmpty()
                ->and($anomalies[0]['type'])->toBe('execution_time_anomaly');
        });
    });

    describe('Data Retention and Cleanup', function () {
        it('can cleanup old audit logs', function () {
            // Create old logs
            createTestAuditLog([
                'account_id' => 1,
                'created_at' => now()->subDays(400),
            ]);
            
            createTestAuditLog([
                'account_id' => 1,
                'created_at' => now()->subDays(100),
            ]);

            $cleanedUp = $this->auditService->cleanupOldLogs(1, 365);

            expect($cleanedUp)->toBe(1);
            expect(PassKitAuditLog::count())->toBe(1);
        });

        it('can archive logs instead of deleting', function () {
            createTestAuditLog([
                'account_id' => 1,
                'created_at' => now()->subDays(400),
                'archived' => false,
            ]);

            $archived = $this->auditService->archiveOldLogs(1, 365);

            expect($archived)->toBe(1);
            
            $log = PassKitAuditLog::first();
            expect($log->archived)->toBeTrue()
                ->and($log->archived_at)->not->toBeNull();
        });

        it('respects retention policies for different event types', function () {
            // GDPR event (longer retention)
            createTestAuditLog([
                'account_id' => 1,
                'gdpr_relevant' => true,
                'created_at' => now()->subDays(400),
            ]);

            // Regular event (shorter retention)
            createTestAuditLog([
                'account_id' => 1,
                'gdpr_relevant' => false,
                'created_at' => now()->subDays(400),
            ]);

            $policies = [
                'gdpr_retention_days' => 2555, // 7 years
                'default_retention_days' => 365, // 1 year
            ];

            $cleanedUp = $this->auditService->cleanupWithRetentionPolicies(1, $policies);

            expect($cleanedUp)->toBe(1); // Only non-GDPR event deleted
            
            $remaining = PassKitAuditLog::count();
            expect($remaining)->toBe(1);
        });
    });
});