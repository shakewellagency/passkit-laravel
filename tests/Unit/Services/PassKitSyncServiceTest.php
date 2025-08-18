<?php

use ShakewellAgency\PassKitLaravel\Services\PassKitSyncService;
use ShakewellAgency\PassKitLaravel\Services\PassKitService;
use ShakewellAgency\PassKitLaravel\Services\PassKitCrudManager;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use Mockery\MockInterface;

describe('PassKitSyncService', function () {
    beforeEach(function () {
        $this->passkitService = Mockery::mock(PassKitService::class);
        $this->crudManager = app(PassKitCrudManager::class);
        $this->syncService = new PassKitSyncService($this->passkitService, $this->crudManager);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('can instantiate the sync service', function () {
        expect($this->syncService)->toBeInstanceOf(PassKitSyncService::class);
    });

    describe('Member Synchronization', function () {
        it('can sync member from PassKit API', function () {
            $memberData = [
                'id' => 'passkit_member_123',
                'externalId' => 'user_456',
                'email' => 'test@example.com',
                'firstName' => 'John',
                'lastName' => 'Doe',
                'points' => 250,
                'tier' => 'gold',
                'status' => 'active',
            ];

            $this->passkitService->shouldReceive('getMember')
                ->with('passkit_member_123')
                ->once()
                ->andReturn($memberData);

            $result = $this->syncService->syncMemberFromApi('passkit_member_123', 1);

            expect($result)->toBeInstanceOf(PassKitMember::class)
                ->and($result->passkit_id)->toBe('passkit_member_123')
                ->and($result->email)->toBe('test@example.com')
                ->and($result->points_balance)->toBe(250);
        });

        it('can sync member to PassKit API', function () {
            $member = createTestMember([
                'passkit_id' => 'passkit_member_123',
                'external_id' => 'user_456',
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'points_balance' => 250,
            ]);

            $this->passkitService->shouldReceive('updateMember')
                ->with('passkit_member_123', Mockery::type('array'))
                ->once()
                ->andReturn([
                    'id' => 'passkit_member_123',
                    'success' => true,
                ]);

            $result = $this->syncService->syncMemberToApi($member);

            expect($result)->toBeArray()
                ->and($result['success'])->toBeTrue();
        });

        it('can sync all members for account', function () {
            $member1 = createTestMember(['account_id' => 1, 'passkit_id' => 'member_1']);
            $member2 = createTestMember(['account_id' => 1, 'passkit_id' => 'member_2']);

            $this->passkitService->shouldReceive('getMember')
                ->times(2)
                ->andReturn([
                    'id' => 'member_1',
                    'points' => 150,
                    'tier' => 'silver',
                ], [
                    'id' => 'member_2',
                    'points' => 300,
                    'tier' => 'gold',
                ]);

            $results = $this->syncService->syncAllMembers(1);

            expect($results)->toHaveKey('synced')
                ->and($results)->toHaveKey('failed')
                ->and($results['synced'])->toBe(2)
                ->and($results['failed'])->toBe(0);
        });

        it('handles failed member sync gracefully', function () {
            $member = createTestMember(['passkit_id' => 'invalid_member']);

            $this->passkitService->shouldReceive('getMember')
                ->with('invalid_member')
                ->once()
                ->andThrow(new \Exception('Member not found'));

            $result = $this->syncService->syncMemberFromApi('invalid_member', 1);

            expect($result)->toBeNull();
        });

        it('can detect member sync conflicts', function () {
            $member = createTestMember([
                'passkit_id' => 'member_123',
                'points_balance' => 100,
                'last_sync_at' => now()->subHours(2),
            ]);

            $apiData = [
                'id' => 'member_123',
                'points' => 150,
                'lastModified' => now()->subHours(1)->toISOString(),
            ];

            $this->passkitService->shouldReceive('getMember')
                ->with('member_123')
                ->once()
                ->andReturn($apiData);

            $conflicts = $this->syncService->detectSyncConflicts($member);

            expect($conflicts)->toBeArray()
                ->and($conflicts)->toHaveKey('points_balance');
        });
    });

    describe('Transaction Synchronization', function () {
        it('can sync transactions from PassKit API', function () {
            $member = createTestMember(['passkit_id' => 'member_123']);

            $transactionData = [
                [
                    'id' => 'txn_1',
                    'member_id' => 'member_123',
                    'type' => 'earn',
                    'points' => 50,
                    'description' => 'Purchase reward',
                    'status' => 'completed',
                    'created_at' => now()->toISOString(),
                ],
                [
                    'id' => 'txn_2',
                    'member_id' => 'member_123',
                    'type' => 'burn',
                    'points' => -25,
                    'description' => 'Redemption',
                    'status' => 'completed',
                    'created_at' => now()->toISOString(),
                ],
            ];

            $this->passkitService->shouldReceive('getMemberTransactions')
                ->with('member_123')
                ->once()
                ->andReturn($transactionData);

            $results = $this->syncService->syncTransactionsFromApi($member);

            expect($results)->toHaveKey('synced')
                ->and($results)->toHaveKey('skipped')
                ->and($results['synced'])->toBe(2);
        });

        it('skips existing transactions during sync', function () {
            $member = createTestMember(['passkit_id' => 'member_123']);
            
            // Create existing transaction
            createTestTransaction([
                'passkit_transaction_id' => 'txn_existing',
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
            ]);

            $transactionData = [
                [
                    'id' => 'txn_existing',
                    'member_id' => 'member_123',
                    'type' => 'earn',
                    'points' => 50,
                ],
                [
                    'id' => 'txn_new',
                    'member_id' => 'member_123',
                    'type' => 'earn',
                    'points' => 75,
                ],
            ];

            $this->passkitService->shouldReceive('getMemberTransactions')
                ->with('member_123')
                ->once()
                ->andReturn($transactionData);

            $results = $this->syncService->syncTransactionsFromApi($member);

            expect($results['synced'])->toBe(1)
                ->and($results['skipped'])->toBe(1);
        });

        it('can sync transaction to PassKit API', function () {
            $transaction = createTestTransaction([
                'passkit_transaction_id' => 'txn_123',
                'transaction_type' => 'earn',
                'points_amount' => 100,
            ]);

            $this->passkitService->shouldReceive('updateTransaction')
                ->with('txn_123', Mockery::type('array'))
                ->once()
                ->andReturn([
                    'id' => 'txn_123',
                    'success' => true,
                ]);

            $result = $this->syncService->syncTransactionToApi($transaction);

            expect($result)->toBeArray()
                ->and($result['success'])->toBeTrue();
        });
    });

    describe('Pass Synchronization', function () {
        it('can sync wallet pass from PassKit API', function () {
            $passData = [
                'id' => 'pass_123',
                'member_id' => 'member_456',
                'status' => 'active',
                'data' => [
                    'points' => 200,
                    'tier' => 'gold',
                ],
                'installed' => true,
                'device_type' => 'ios',
            ];

            $this->passkitService->shouldReceive('getPass')
                ->with('pass_123')
                ->once()
                ->andReturn($passData);

            $result = $this->syncService->syncPassFromApi('pass_123', 1, 1);

            expect($result)->toBeInstanceOf(WalletPass::class)
                ->and($result->passkit_id)->toBe('pass_123')
                ->and($result->status)->toBe('active')
                ->and($result->is_installed)->toBeTrue();
        });

        it('can sync wallet pass to PassKit API', function () {
            $pass = createTestWalletPass([
                'passkit_id' => 'pass_123',
                'pass_data' => ['points' => 150],
                'status' => 'active',
            ]);

            $this->passkitService->shouldReceive('updatePass')
                ->with('pass_123', Mockery::type('array'))
                ->once()
                ->andReturn([
                    'id' => 'pass_123',
                    'success' => true,
                ]);

            $result = $this->syncService->syncPassToApi($pass);

            expect($result)->toBeArray()
                ->and($result['success'])->toBeTrue();
        });

        it('can sync all passes for user', function () {
            $pass1 = createTestWalletPass(['user_id' => 1, 'passkit_id' => 'pass_1']);
            $pass2 = createTestWalletPass(['user_id' => 1, 'passkit_id' => 'pass_2']);

            $this->passkitService->shouldReceive('getPass')
                ->times(2)
                ->andReturn([
                    'id' => 'pass_1',
                    'status' => 'active',
                    'data' => ['points' => 100],
                ], [
                    'id' => 'pass_2',
                    'status' => 'active',
                    'data' => ['points' => 200],
                ]);

            $results = $this->syncService->syncAllPassesForUser(1);

            expect($results)->toHaveKey('synced')
                ->and($results)->toHaveKey('failed')
                ->and($results['synced'])->toBe(2);
        });
    });

    describe('Bulk Synchronization', function () {
        it('can perform full account sync', function () {
            $program = createTestProgram(['account_id' => 1]);
            $member = createTestMember(['account_id' => 1, 'passkit_id' => 'member_123']);
            $pass = createTestWalletPass(['account_id' => 1, 'passkit_id' => 'pass_123']);

            $this->passkitService->shouldReceive('getMember')
                ->once()
                ->andReturn([
                    'id' => 'member_123',
                    'points' => 250,
                ]);

            $this->passkitService->shouldReceive('getMemberTransactions')
                ->once()
                ->andReturn([]);

            $this->passkitService->shouldReceive('getPass')
                ->once()
                ->andReturn([
                    'id' => 'pass_123',
                    'status' => 'active',
                ]);

            $results = $this->syncService->performFullSync(1);

            expect($results)->toHaveKeys(['members', 'transactions', 'passes'])
                ->and($results['members']['synced'])->toBe(1)
                ->and($results['passes']['synced'])->toBe(1);
        });

        it('can sync with time-based filtering', function () {
            $member = createTestMember([
                'account_id' => 1,
                'passkit_id' => 'member_123',
                'last_sync_at' => now()->subDays(2),
            ]);

            $this->passkitService->shouldReceive('getMembersSince')
                ->with(Mockery::type('string'))
                ->once()
                ->andReturn([
                    [
                        'id' => 'member_123',
                        'points' => 300,
                        'lastModified' => now()->toISOString(),
                    ],
                ]);

            $results = $this->syncService->syncSince(1, now()->subDays(1));

            expect($results)->toHaveKey('members_synced')
                ->and($results['members_synced'])->toBe(1);
        });

        it('can handle large batch synchronization', function () {
            // Create 50 members
            for ($i = 0; $i < 50; $i++) {
                createTestMember([
                    'account_id' => 1,
                    'passkit_id' => "member_{$i}",
                ]);
            }

            $this->passkitService->shouldReceive('getMember')
                ->times(50)
                ->andReturn([
                    'id' => 'member_test',
                    'points' => 100,
                ]);

            $results = $this->syncService->syncAllMembers(1, ['batch_size' => 10]);

            expect($results['synced'])->toBe(50)
                ->and($results)->toHaveKey('batches_processed');
        });
    });

    describe('Sync Status and Monitoring', function () {
        it('can get sync status for account', function () {
            $member = createTestMember([
                'account_id' => 1,
                'last_sync_at' => now()->subHours(2),
                'sync_pending' => false,
            ]);

            $status = $this->syncService->getSyncStatus(1);

            expect($status)->toHaveKeys([
                'last_sync_at',
                'members_pending_sync',
                'passes_pending_sync',
                'sync_health',
            ])
                ->and($status['members_pending_sync'])->toBe(0);
        });

        it('can mark entities as sync pending', function () {
            $member = createTestMember(['sync_pending' => false]);

            $this->syncService->markSyncPending($member);

            $member->refresh();
            expect($member->sync_pending)->toBeTrue();
        });

        it('can clear sync pending status', function () {
            $member = createTestMember(['sync_pending' => true]);

            $this->syncService->clearSyncPending($member);

            $member->refresh();
            expect($member->sync_pending)->toBeFalse()
                ->and($member->last_sync_at)->not->toBeNull();
        });

        it('can get sync conflicts report', function () {
            $member1 = createTestMember([
                'account_id' => 1,
                'points_balance' => 100,
                'last_sync_at' => now()->subHours(3),
            ]);
            
            $member2 = createTestMember([
                'account_id' => 1,
                'points_balance' => 200,
                'last_sync_at' => now()->subMinutes(10),
            ]);

            $this->passkitService->shouldReceive('getMember')
                ->times(2)
                ->andReturn([
                    'id' => $member1->passkit_id,
                    'points' => 150, // Conflict
                    'lastModified' => now()->subHours(1)->toISOString(),
                ], [
                    'id' => $member2->passkit_id,
                    'points' => 200, // No conflict
                    'lastModified' => now()->subMinutes(30)->toISOString(),
                ]);

            $conflicts = $this->syncService->getSyncConflicts(1);

            expect($conflicts)->toHaveCount(1)
                ->and($conflicts[0]['entity_type'])->toBe('member')
                ->and($conflicts[0]['conflict_fields'])->toContain('points_balance');
        });
    });

    describe('Error Handling and Recovery', function () {
        it('handles API errors during sync', function () {
            $member = createTestMember(['passkit_id' => 'member_123']);

            $this->passkitService->shouldReceive('getMember')
                ->with('member_123')
                ->once()
                ->andThrow(new \Exception('API Error'));

            $result = $this->syncService->syncMemberFromApi('member_123', 1);

            expect($result)->toBeNull();
        });

        it('can retry failed sync operations', function () {
            $member = createTestMember(['passkit_id' => 'member_123']);

            $this->passkitService->shouldReceive('getMember')
                ->times(3)
                ->andThrow(new \Exception('Temporary error'))
                ->andThrow(new \Exception('Still failing'))
                ->andReturn([
                    'id' => 'member_123',
                    'points' => 250,
                ]);

            $result = $this->syncService->syncMemberFromApi('member_123', 1, ['max_retries' => 3]);

            expect($result)->toBeInstanceOf(PassKitMember::class)
                ->and($result->points_balance)->toBe(250);
        });

        it('logs sync failures for monitoring', function () {
            $member = createTestMember(['passkit_id' => 'member_123']);

            $this->passkitService->shouldReceive('getMember')
                ->with('member_123')
                ->once()
                ->andThrow(new \Exception('Critical sync error'));

            $result = $this->syncService->syncMemberFromApi('member_123', 1);

            expect($result)->toBeNull();
            
            // Check that sync failure was logged
            $syncLog = \ShakewellAgency\PassKitLaravel\Models\PassKitAuditLog::where('event_type', 'sync_failed')
                ->where('entity_type', 'member')
                ->where('entity_id', $member->id)
                ->first();

            expect($syncLog)->not->toBeNull()
                ->and($syncLog->status)->toBe('failed');
        });

        it('can recover from partial sync failures', function () {
            $member1 = createTestMember(['account_id' => 1, 'passkit_id' => 'member_1']);
            $member2 = createTestMember(['account_id' => 1, 'passkit_id' => 'member_2']);
            $member3 = createTestMember(['account_id' => 1, 'passkit_id' => 'member_3']);

            $this->passkitService->shouldReceive('getMember')
                ->times(3)
                ->andReturn([
                    'id' => 'member_1',
                    'points' => 100,
                ])
                ->andThrow(new \Exception('Member 2 error'))
                ->andReturn([
                    'id' => 'member_3',
                    'points' => 300,
                ]);

            $results = $this->syncService->syncAllMembers(1);

            expect($results['synced'])->toBe(2)
                ->and($results['failed'])->toBe(1);
        });
    });
});