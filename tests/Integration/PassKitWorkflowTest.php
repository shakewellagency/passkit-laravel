<?php

use ShakewellAgency\PassKitLaravel\Services\PassKitService;
use ShakewellAgency\PassKitLaravel\Services\PassKitCrudManager;
use ShakewellAgency\PassKitLaravel\Services\PassKitSyncService;
use ShakewellAgency\PassKitLaravel\Services\PassKitAuditService;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use ShakewellAgency\PassKitLaravel\Models\PassKitAuditLog;
use Mockery\MockInterface;

describe('PassKit Integration Workflow Tests', function () {
    beforeEach(function () {
        $this->passkitService = Mockery::mock(PassKitService::class);
        $this->app->instance(PassKitService::class, $this->passkitService);
        
        $this->crudManager = app(PassKitCrudManager::class);
        $this->syncService = new PassKitSyncService($this->passkitService, $this->crudManager);
        $this->auditService = app(PassKitAuditService::class);
    });

    afterEach(function () {
        Mockery::close();
    });

    describe('Complete Member Lifecycle', function () {
        it('can create member, add points, and sync with PassKit', function () {
            // 1. Create program
            $program = createTestProgram([
                'name' => 'VIP Membership',
                'account_id' => 1,
                'passkit_id' => 'program_123',
            ]);

            // 2. Mock PassKit API responses
            $this->passkitService->shouldReceive('enrollMember')
                ->once()
                ->andReturn(['id' => 'passkit_member_456']);

            $this->passkitService->shouldReceive('updateMemberPoints')
                ->once()
                ->andReturn(['id' => 'passkit_member_456', 'points' => 150]);

            $this->passkitService->shouldReceive('getMember')
                ->once()
                ->andReturn([
                    'id' => 'passkit_member_456',
                    'externalId' => 'user_789',
                    'email' => 'test@example.com',
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'points' => 150,
                    'tier' => 'silver',
                ]);

            // 3. Create member
            $member = $this->crudManager->createMember([
                'external_id' => 'user_789',
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'program_id' => $program->id,
                'account_id' => 1,
                'points_balance' => 100,
            ]);

            expect($member)->toBeInstanceOf(PassKitMember::class)
                ->and($member->passkit_id)->toBe('passkit_member_456');

            // 4. Add points transaction
            $transaction = $this->crudManager->createTransaction([
                'passkit_transaction_id' => 'txn_789',
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'transaction_type' => 'earn',
                'points_amount' => 50,
                'description' => 'Purchase reward',
                'status' => 'completed',
            ]);

            expect($transaction)->toBeInstanceOf(PassKitTransaction::class)
                ->and($transaction->points_amount)->toBe(50);

            // 5. Update member points
            $updatedMember = $this->crudManager->updateMember($member->id, [
                'points_balance' => 150,
            ]);

            expect($updatedMember->points_balance)->toBe(150);

            // 6. Sync with PassKit API
            $syncResult = $this->syncService->syncMemberFromApi($member->passkit_id, 1);

            expect($syncResult)->toBeInstanceOf(PassKitMember::class)
                ->and($syncResult->points_balance)->toBe(150);

            // 7. Verify audit trail
            $auditLogs = PassKitAuditLog::where('entity_type', 'member')
                ->where('entity_id', $member->id)
                ->get();

            expect($auditLogs->count())->toBeGreaterThan(0);
        });

        it('can handle member enrollment with wallet pass creation', function () {
            // 1. Create program and template
            $program = createTestProgram(['account_id' => 1]);
            $template = createTestCardTemplate(['account_id' => 1]);

            // 2. Mock PassKit responses
            $this->passkitService->shouldReceive('enrollMember')
                ->once()
                ->andReturn(['id' => 'member_123']);

            $this->passkitService->shouldReceive('getPassInstallationPackage')
                ->once()
                ->andReturn([
                    'urls' => [
                        'apple' => 'https://wallet.apple.com/test',
                        'google' => 'https://pay.google.com/test',
                    ],
                    'qr_codes' => [
                        'apple' => 'data:image/png;base64,test_apple',
                        'google' => 'data:image/png;base64,test_google',
                    ],
                ]);

            // 3. Enroll member with wallet pass
            $result = $this->passkitService->enrollMemberWithWalletPass(
                'gold_tier',
                [
                    'externalId' => 'user_123',
                    'email' => 'test@example.com',
                    'firstName' => 'Jane',
                    'lastName' => 'Smith',
                    'points' => 200,
                ],
                1, // user_id
                1  // account_id
            );

            expect($result)->toHaveKeys(['member_id', 'wallet_pass', 'install_urls', 'qr_codes'])
                ->and($result['member_id'])->toBe('member_123')
                ->and($result['install_urls'])->toHaveKey('apple')
                ->and($result['qr_codes'])->toHaveKey('google');

            // 4. Verify wallet pass was created in database
            $walletPass = WalletPass::where('member_passkit_id', 'member_123')->first();
            expect($walletPass)->not->toBeNull()
                ->and($walletPass->install_urls)->toHaveKey('apple');
        });
    });

    describe('Points Management Workflow', function () {
        it('can handle complex points earning and spending scenario', function () {
            $member = createTestMember([
                'passkit_id' => 'member_456',
                'points_balance' => 100,
                'account_id' => 1,
            ]);

            // Mock PassKit point operations
            $this->passkitService->shouldReceive('updateMemberPoints')
                ->times(3)
                ->andReturn(
                    ['id' => 'member_456', 'points' => 150],
                    ['id' => 'member_456', 'points' => 200],
                    ['id' => 'member_456', 'points' => 150]
                );

            // 1. Customer makes purchase - earns points
            $earnTransaction = $this->crudManager->createTransaction([
                'passkit_transaction_id' => 'txn_earn_1',
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'transaction_type' => 'earn',
                'points_amount' => 50,
                'points_balance_before' => 100,
                'points_balance_after' => 150,
                'description' => 'Purchase at Store A',
                'purchase_amount' => 25.99,
                'status' => 'completed',
            ]);

            // 2. Customer gets bonus points
            $bonusTransaction = $this->crudManager->createTransaction([
                'passkit_transaction_id' => 'txn_bonus_1',
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'transaction_type' => 'bonus',
                'points_amount' => 50,
                'points_balance_before' => 150,
                'points_balance_after' => 200,
                'description' => 'Birthday bonus',
                'status' => 'completed',
            ]);

            // 3. Customer redeems points
            $redeemTransaction = $this->crudManager->createTransaction([
                'passkit_transaction_id' => 'txn_redeem_1',
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'transaction_type' => 'burn',
                'points_amount' => -50,
                'points_balance_before' => 200,
                'points_balance_after' => 150,
                'description' => 'Redeemed for $5 discount',
                'status' => 'completed',
            ]);

            // 4. Update member balance
            $member = $this->crudManager->updateMember($member->id, [
                'points_balance' => 150,
            ]);

            // 5. Verify transaction summary
            $summary = PassKitTransaction::getTransactionSummary($member->passkit_id);

            expect($summary['total_earned'])->toBe(100) // 50 + 50
                ->and($summary['total_spent'])->toBe(50)
                ->and($summary['transaction_count'])->toBe(3)
                ->and($member->points_balance)->toBe(150);

            // 6. Verify audit logs for all operations
            $auditCount = PassKitAuditLog::where('entity_type', 'transaction')
                ->whereIn('entity_id', [$earnTransaction->id, $bonusTransaction->id, $redeemTransaction->id])
                ->count();

            expect($auditCount)->toBe(3);
        });

        it('can handle points expiration workflow', function () {
            $member = createTestMember([
                'passkit_id' => 'member_789',
                'points_balance' => 500,
            ]);

            // Create transactions with different expiration dates
            $this->crudManager->createTransaction([
                'passkit_transaction_id' => 'txn_old',
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'transaction_type' => 'earn',
                'points_amount' => 100,
                'expires_at' => now()->subDays(1), // Expired
                'is_expired' => true,
                'status' => 'completed',
            ]);

            $this->crudManager->createTransaction([
                'passkit_transaction_id' => 'txn_new',
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'transaction_type' => 'earn',
                'points_amount' => 200,
                'expires_at' => now()->addDays(30), // Valid
                'is_expired' => false,
                'status' => 'completed',
            ]);

            // Process expiration
            $expiredTransactions = PassKitTransaction::expired()->get();
            expect($expiredTransactions)->toHaveCount(1)
                ->and($expiredTransactions->first()->is_expired)->toBeTrue();

            // Verify member's effective balance (excluding expired points)
            $validPoints = PassKitTransaction::where('member_passkit_id', $member->passkit_id)
                ->where('is_expired', false)
                ->where('transaction_type', 'earn')
                ->sum('points_amount');

            expect($validPoints)->toBe(200);
        });
    });

    describe('Synchronization Workflow', function () {
        it('can handle bi-directional sync between local and PassKit', function () {
            // 1. Create local member
            $member = createTestMember([
                'passkit_id' => 'sync_member_123',
                'points_balance' => 100,
                'account_id' => 1,
                'last_sync_at' => now()->subHours(2),
            ]);

            // 2. Mock PassKit API returning updated data
            $this->passkitService->shouldReceive('getMember')
                ->once()
                ->andReturn([
                    'id' => 'sync_member_123',
                    'externalId' => $member->external_id,
                    'email' => $member->email,
                    'firstName' => $member->first_name,
                    'lastName' => $member->last_name,
                    'points' => 250, // Updated on PassKit side
                    'tier' => 'gold',
                    'lastModified' => now()->subHours(1)->toISOString(),
                ]);

            $this->passkitService->shouldReceive('getMemberTransactions')
                ->once()
                ->andReturn([
                    [
                        'id' => 'txn_sync_1',
                        'member_id' => 'sync_member_123',
                        'type' => 'earn',
                        'points' => 150,
                        'description' => 'Store purchase',
                        'status' => 'completed',
                        'created_at' => now()->subHours(1)->toISOString(),
                    ],
                ]);

            // 3. Perform sync
            $syncResult = $this->syncService->syncMemberFromApi('sync_member_123', 1);
            $transactionSyncResult = $this->syncService->syncTransactionsFromApi($member);

            // 4. Verify member was updated
            $member->refresh();
            expect($member->points_balance)->toBe(250)
                ->and($member->last_sync_at)->not->toBeNull();

            // 5. Verify transaction was created
            $transaction = PassKitTransaction::where('passkit_transaction_id', 'txn_sync_1')->first();
            expect($transaction)->not->toBeNull()
                ->and($transaction->points_amount)->toBe(150);

            // 6. Verify sync results
            expect($transactionSyncResult['synced'])->toBe(1)
                ->and($transactionSyncResult['skipped'])->toBe(0);
        });

        it('can detect and handle sync conflicts', function () {
            $member = createTestMember([
                'passkit_id' => 'conflict_member_456',
                'points_balance' => 100,
                'email' => 'local@example.com',
                'last_sync_at' => now()->subHours(2),
            ]);

            // Mock PassKit returning different data
            $this->passkitService->shouldReceive('getMember')
                ->once()
                ->andReturn([
                    'id' => 'conflict_member_456',
                    'email' => 'remote@example.com', // Different email
                    'points' => 200, // Different points
                    'lastModified' => now()->subHours(1)->toISOString(),
                ]);

            // Detect conflicts
            $conflicts = $this->syncService->detectSyncConflicts($member);

            expect($conflicts)->toBeArray()
                ->and($conflicts)->toHaveKey('email')
                ->and($conflicts)->toHaveKey('points_balance')
                ->and($conflicts['email']['local'])->toBe('local@example.com')
                ->and($conflicts['email']['remote'])->toBe('remote@example.com');

            // Log conflict for audit
            $this->auditService->log([
                'event_type' => 'sync_conflict',
                'entity_type' => 'member',
                'entity_id' => $member->id,
                'account_id' => $member->account_id,
                'metadata' => ['conflicts' => $conflicts],
                'requires_approval' => true,
            ]);

            $conflictLog = PassKitAuditLog::where('event_type', 'sync_conflict')
                ->where('entity_id', $member->id)
                ->first();

            expect($conflictLog)->not->toBeNull()
                ->and($conflictLog->requires_approval)->toBeTrue();
        });
    });

    describe('Error Handling and Recovery', function () {
        it('can handle partial failures during batch operations', function () {
            // Create test members
            $member1 = createTestMember(['passkit_id' => 'member_1', 'account_id' => 1]);
            $member2 = createTestMember(['passkit_id' => 'member_2', 'account_id' => 1]);
            $member3 = createTestMember(['passkit_id' => 'member_3', 'account_id' => 1]);

            // Mock API responses with one failure
            $this->passkitService->shouldReceive('getMember')
                ->times(3)
                ->andReturn(
                    ['id' => 'member_1', 'points' => 100], // Success
                    ['id' => 'member_3', 'points' => 300]  // Success
                )
                ->andThrow(new \Exception('Member 2 API error')); // Failure

            // Perform batch sync
            $results = $this->syncService->syncAllMembers(1);

            // Verify partial success
            expect($results['synced'])->toBe(2)
                ->and($results['failed'])->toBe(1);

            // Verify successful members were updated
            $member1->refresh();
            $member3->refresh();
            expect($member1->points_balance)->toBe(100)
                ->and($member3->points_balance)->toBe(300);

            // Verify failed member wasn't updated
            $member2->refresh();
            expect($member2->last_sync_at)->toBeNull();

            // Verify error was logged
            $errorLog = PassKitAuditLog::where('event_type', 'sync_failed')
                ->where('entity_id', $member2->id)
                ->first();

            expect($errorLog)->not->toBeNull()
                ->and($errorLog->status)->toBe('failed');
        });

        it('can handle API rate limiting and retry logic', function () {
            $member = createTestMember(['passkit_id' => 'rate_limited_member']);

            // Mock rate limiting then success
            $this->passkitService->shouldReceive('getMember')
                ->times(3)
                ->andThrow(new \Exception('Rate limit exceeded'))
                ->andThrow(new \Exception('Still rate limited'))
                ->andReturn(['id' => 'rate_limited_member', 'points' => 500]);

            // Perform sync with retry logic
            $result = $this->syncService->syncMemberFromApi(
                'rate_limited_member', 
                1, 
                ['max_retries' => 3, 'retry_delay' => 100]
            );

            expect($result)->toBeInstanceOf(PassKitMember::class)
                ->and($result->points_balance)->toBe(500);

            // Verify retry attempts were logged
            $retryLogs = PassKitAuditLog::where('event_type', 'sync_retry')
                ->where('entity_id', $member->id)
                ->count();

            expect($retryLogs)->toBe(2); // Two retry attempts before success
        });
    });

    describe('Performance and Monitoring', function () {
        it('can track performance metrics during operations', function () {
            $member = createTestMember(['account_id' => 1]);

            // Mock slow API response
            $this->passkitService->shouldReceive('getMember')
                ->once()
                ->andReturnUsing(function () {
                    usleep(100000); // 100ms delay
                    return ['id' => 'perf_member', 'points' => 100];
                });

            $startTime = microtime(true);
            
            // Perform operation
            $result = $this->syncService->syncMemberFromApi($member->passkit_id, 1);
            
            $executionTime = (microtime(true) - $startTime) * 1000;

            // Log performance
            $this->auditService->logPerformanceEvent([
                'event_type' => 'member_sync_performance',
                'account_id' => 1,
                'operation_type' => 'member_sync',
                'execution_time_ms' => $executionTime,
                'performance_threshold_exceeded' => $executionTime > 50,
                'performance_grade' => $executionTime > 100 ? 'D' : 'B',
            ]);

            // Verify performance was tracked
            $perfLog = PassKitAuditLog::where('event_type', 'member_sync_performance')
                ->first();

            expect($perfLog)->not->toBeNull()
                ->and($perfLog->execution_time_ms)->toBeGreaterThan(50);
        });

        it('can generate comprehensive audit reports', function () {
            $accountId = 1;

            // Create various audit logs
            $this->auditService->log([
                'event_type' => 'member_created',
                'account_id' => $accountId,
                'status' => 'success',
            ]);

            $this->auditService->logSecurityEvent([
                'event_type' => 'api_access',
                'account_id' => $accountId,
                'security_level' => 'normal',
            ]);

            $this->auditService->logPerformanceEvent([
                'event_type' => 'sync_performance',
                'account_id' => $accountId,
                'execution_time_ms' => 150,
                'performance_grade' => 'B',
            ]);

            // Generate compliance report
            $report = $this->auditService->generateComplianceReport($accountId, [
                'period_days' => 1,
                'include_performance' => true,
                'include_security' => true,
            ]);

            expect($report)->toHaveKeys([
                'period',
                'total_events',
                'security_events',
                'performance_events',
                'compliance_score',
            ])
                ->and($report['total_events'])->toBe(3)
                ->and($report['security_events'])->toBe(1)
                ->and($report['performance_events'])->toBe(1);
        });
    });

    describe('Data Consistency and Integrity', function () {
        it('maintains data consistency across related entities', function () {
            // Create related entities
            $program = createTestProgram(['account_id' => 1]);
            $member = createTestMember([
                'program_id' => $program->id,
                'account_id' => 1,
                'points_balance' => 100,
            ]);
            $pass = createTestWalletPass([
                'user_id' => 1,
                'account_id' => 1,
                'member_passkit_id' => $member->passkit_id,
                'pass_data' => ['points' => 100],
            ]);

            // Create transaction that affects points
            $transaction = $this->crudManager->createTransaction([
                'passkit_transaction_id' => 'consistency_txn',
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'transaction_type' => 'earn',
                'points_amount' => 50,
                'points_balance_before' => 100,
                'points_balance_after' => 150,
            ]);

            // Update member points
            $member = $this->crudManager->updateMember($member->id, [
                'points_balance' => 150,
            ]);

            // Update pass data to match
            $pass = $this->crudManager->updateWalletPass($pass->id, [
                'pass_data' => ['points' => 150],
            ]);

            // Verify consistency
            expect($member->points_balance)->toBe(150)
                ->and($pass->pass_data['points'])->toBe(150)
                ->and($transaction->points_balance_after)->toBe(150);

            // Verify relationships are intact
            expect($member->program_id)->toBe($program->id)
                ->and($pass->member_passkit_id)->toBe($member->passkit_id);
        });
    });
});