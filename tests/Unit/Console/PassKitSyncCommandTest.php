<?php

use ShakewellAgency\PassKitLaravel\Console\Commands\PassKitSync;
use ShakewellAgency\PassKitLaravel\Services\PassKitSyncService;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use Mockery\MockInterface;

describe('PassKitSync Console Command', function () {
    beforeEach(function () {
        $this->mockSyncService = Mockery::mock(PassKitSyncService::class);
        $this->app->instance(PassKitSyncService::class, $this->mockSyncService);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('can run sync command for all accounts', function () {
        $this->mockSyncService->shouldReceive('performFullSync')
            ->once()
            ->with(1)
            ->andReturn([
                'members' => ['synced' => 5, 'failed' => 0],
                'transactions' => ['synced' => 10, 'failed' => 0],
                'passes' => ['synced' => 3, 'failed' => 0],
            ]);

        // Create test account
        createTestProgram(['account_id' => 1]);

        $this->artisan('passkit:sync')
            ->expectsOutput('Starting PassKit synchronization...')
            ->expectsOutput('Account 1: 5 members, 10 transactions, 3 passes synced')
            ->expectsOutput('Synchronization completed successfully!')
            ->assertExitCode(0);
    });

    it('can sync specific account', function () {
        $this->mockSyncService->shouldReceive('performFullSync')
            ->once()
            ->with(1)
            ->andReturn([
                'members' => ['synced' => 3, 'failed' => 0],
                'transactions' => ['synced' => 5, 'failed' => 0],
                'passes' => ['synced' => 2, 'failed' => 0],
            ]);

        $this->artisan('passkit:sync', ['--account' => 1])
            ->expectsOutput('Syncing account: 1')
            ->expectsOutput('Account 1: 3 members, 5 transactions, 2 passes synced')
            ->assertExitCode(0);
    });

    it('can sync only members', function () {
        $this->mockSyncService->shouldReceive('syncAllMembers')
            ->once()
            ->with(1)
            ->andReturn(['synced' => 8, 'failed' => 1]);

        createTestProgram(['account_id' => 1]);

        $this->artisan('passkit:sync', ['--members-only' => true])
            ->expectsOutput('Syncing members only...')
            ->expectsOutput('Account 1: 8 members synced, 1 failed')
            ->assertExitCode(0);
    });

    it('can sync only transactions', function () {
        $member = createTestMember(['account_id' => 1]);

        $this->mockSyncService->shouldReceive('syncTransactionsFromApi')
            ->once()
            ->with($member)
            ->andReturn(['synced' => 5, 'skipped' => 2]);

        $this->artisan('passkit:sync', ['--transactions-only' => true])
            ->expectsOutput('Syncing transactions only...')
            ->expectsOutput('Account 1: 5 transactions synced, 2 skipped')
            ->assertExitCode(0);
    });

    it('can sync only wallet passes', function () {
        $this->mockSyncService->shouldReceive('syncAllPassesForUser')
            ->once()
            ->andReturn(['synced' => 3, 'failed' => 0]);

        createTestWalletPass(['account_id' => 1, 'user_id' => 1]);

        $this->artisan('passkit:sync', ['--passes-only' => true])
            ->expectsOutput('Syncing wallet passes only...')
            ->expectsOutput('3 passes synced, 0 failed')
            ->assertExitCode(0);
    });

    it('can sync with time-based filtering', function () {
        $this->mockSyncService->shouldReceive('syncSince')
            ->once()
            ->with(1, Mockery::type(\Carbon\Carbon::class))
            ->andReturn(['members_synced' => 3, 'transactions_synced' => 7]);

        createTestProgram(['account_id' => 1]);

        $this->artisan('passkit:sync', ['--since' => '2024-01-01'])
            ->expectsOutput('Syncing changes since: 2024-01-01')
            ->expectsOutput('Account 1: 3 members, 7 transactions synced')
            ->assertExitCode(0);
    });

    it('can sync with batch size option', function () {
        $this->mockSyncService->shouldReceive('syncAllMembers')
            ->once()
            ->with(1, ['batch_size' => 50])
            ->andReturn(['synced' => 100, 'failed' => 0, 'batches_processed' => 2]);

        createTestProgram(['account_id' => 1]);

        $this->artisan('passkit:sync', ['--batch-size' => 50, '--members-only' => true])
            ->expectsOutput('Using batch size: 50')
            ->expectsOutput('Account 1: 100 members synced, 0 failed (2 batches)')
            ->assertExitCode(0);
    });

    it('can run dry run without making changes', function () {
        $this->mockSyncService->shouldReceive('getSyncStatus')
            ->once()
            ->with(1)
            ->andReturn([
                'members_pending_sync' => 5,
                'passes_pending_sync' => 3,
                'last_sync_at' => now()->subHours(2)->toDateTimeString(),
            ]);

        createTestProgram(['account_id' => 1]);

        $this->artisan('passkit:sync', ['--dry-run' => true])
            ->expectsOutput('DRY RUN - No changes will be made')
            ->expectsOutput('Account 1: 5 members and 3 passes pending sync')
            ->expectsOutput('Last sync: 2 hours ago')
            ->assertExitCode(0);
    });

    it('can force sync even with recent sync', function () {
        $this->mockSyncService->shouldReceive('performFullSync')
            ->once()
            ->with(1)
            ->andReturn([
                'members' => ['synced' => 2, 'failed' => 0],
                'transactions' => ['synced' => 3, 'failed' => 0],
                'passes' => ['synced' => 1, 'failed' => 0],
            ]);

        $this->artisan('passkit:sync', ['--account' => 1, '--force' => true])
            ->expectsOutput('Force sync enabled - ignoring recent sync timestamps')
            ->expectsOutput('Account 1: 2 members, 3 transactions, 1 passes synced')
            ->assertExitCode(0);
    });

    it('can show sync status without syncing', function () {
        $this->mockSyncService->shouldReceive('getSyncStatus')
            ->once()
            ->with(1)
            ->andReturn([
                'last_sync_at' => now()->subHours(1)->toDateTimeString(),
                'members_pending_sync' => 2,
                'passes_pending_sync' => 1,
                'sync_health' => 'good',
            ]);

        createTestProgram(['account_id' => 1]);

        $this->artisan('passkit:sync', ['--status' => true])
            ->expectsOutput('PassKit Sync Status Report')
            ->expectsOutput('Account 1:')
            ->expectsOutput('  Last sync: 1 hour ago')
            ->expectsOutput('  Pending: 2 members, 1 passes')
            ->expectsOutput('  Health: good')
            ->assertExitCode(0);
    });

    it('handles sync failures gracefully', function () {
        $this->mockSyncService->shouldReceive('performFullSync')
            ->once()
            ->with(1)
            ->andReturn([
                'members' => ['synced' => 3, 'failed' => 2],
                'transactions' => ['synced' => 5, 'failed' => 1],
                'passes' => ['synced' => 1, 'failed' => 1],
            ]);

        createTestProgram(['account_id' => 1]);

        $this->artisan('passkit:sync')
            ->expectsOutput('Account 1: 3 members, 5 transactions, 1 passes synced')
            ->expectsOutput('Account 1: 2 members, 1 transactions, 1 passes failed')
            ->assertExitCode(0);
    });

    it('can show detailed sync conflicts', function () {
        $this->mockSyncService->shouldReceive('getSyncConflicts')
            ->once()
            ->with(1)
            ->andReturn([
                [
                    'entity_type' => 'member',
                    'entity_id' => 123,
                    'conflict_fields' => ['points_balance'],
                    'local_value' => 100,
                    'remote_value' => 150,
                ],
            ]);

        createTestProgram(['account_id' => 1]);

        $this->artisan('passkit:sync', ['--show-conflicts' => true])
            ->expectsOutput('Sync Conflicts Report')
            ->expectsOutput('Account 1:')
            ->expectsOutput('  Member 123: points_balance (local: 100, remote: 150)')
            ->assertExitCode(0);
    });

    it('can sync specific member by ID', function () {
        $member = createTestMember(['passkit_id' => 'member_123']);

        $this->mockSyncService->shouldReceive('syncMemberFromApi')
            ->once()
            ->with('member_123', $member->account_id)
            ->andReturn($member);

        $this->artisan('passkit:sync', ['--member-id' => 'member_123'])
            ->expectsOutput('Syncing specific member: member_123')
            ->expectsOutput('Member member_123 synced successfully')
            ->assertExitCode(0);
    });

    it('can sync specific wallet pass by ID', function () {
        $pass = createTestWalletPass(['passkit_id' => 'pass_456']);

        $this->mockSyncService->shouldReceive('syncPassFromApi')
            ->once()
            ->with('pass_456', $pass->user_id, $pass->account_id)
            ->andReturn($pass);

        $this->artisan('passkit:sync', ['--pass-id' => 'pass_456'])
            ->expectsOutput('Syncing specific pass: pass_456')
            ->expectsOutput('Pass pass_456 synced successfully')
            ->assertExitCode(0);
    });

    it('displays progress for large sync operations', function () {
        $this->mockSyncService->shouldReceive('syncAllMembers')
            ->once()
            ->with(1, ['batch_size' => 10])
            ->andReturn([
                'synced' => 100,
                'failed' => 5,
                'batches_processed' => 10,
            ]);

        // Create many members to trigger progress display
        for ($i = 0; $i < 50; $i++) {
            createTestMember(['account_id' => 1]);
        }

        $this->artisan('passkit:sync', ['--members-only' => true, '--batch-size' => 10])
            ->expectsOutput('Processing 50 members in batches of 10...')
            ->expectsOutput('Account 1: 100 members synced, 5 failed (10 batches)')
            ->assertExitCode(0);
    });

    it('can schedule automatic sync', function () {
        $this->artisan('passkit:sync', ['--schedule' => true])
            ->expectsOutput('Setting up automatic sync schedule...')
            ->expectsOutput('Automatic sync scheduled to run every hour')
            ->assertExitCode(0);
    });

    it('handles no accounts to sync', function () {
        $this->artisan('passkit:sync')
            ->expectsOutput('No PassKit accounts found to sync')
            ->assertExitCode(0);
    });

    it('can export sync report', function () {
        $this->mockSyncService->shouldReceive('performFullSync')
            ->once()
            ->with(1)
            ->andReturn([
                'members' => ['synced' => 10, 'failed' => 1],
                'transactions' => ['synced' => 25, 'failed' => 0],
                'passes' => ['synced' => 5, 'failed' => 0],
            ]);

        createTestProgram(['account_id' => 1]);

        $this->artisan('passkit:sync', ['--export-report' => true])
            ->expectsOutput('Sync report exported to: ')
            ->assertExitCode(0);
    });
});