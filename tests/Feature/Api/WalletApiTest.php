<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;

describe('Shakewell Wallet API', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        // Set up test API key
        Config::set('passkit.api_keys', [
            [
                'key' => 'test_api_key_123',
                'account_id' => 1,
                'name' => 'Test API Key',
                'permissions' => ['read', 'write'],
                'rate_limit' => 1000,
                'active' => true
            ]
        ]);
    });

    it('requires authentication for all endpoints', function () {
        $response = $this->getJson('/api/wallet/programs');
        
        expect($response->status())->toBe(401)
            ->and($response->json('success'))->toBeFalse()
            ->and($response->json('message'))->toBe('API key required');
    });

    it('rejects invalid API keys', function () {
        $response = $this->getJson('/api/wallet/programs', [
            'Authorization' => 'Bearer invalid_key'
        ]);
        
        expect($response->status())->toBe(401)
            ->and($response->json('success'))->toBeFalse()
            ->and($response->json('message'))->toBe('Invalid API key');
    });

    describe('Programs API', function () {
        it('can list programs', function () {
            $program = createTestProgram([
                'name' => 'Test Loyalty Program',
                'account_id' => 1
            ]);

            $response = $this->getJson('/api/wallet/programs', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.programs'))->toHaveCount(1)
                ->and($response->json('data.programs.0.name'))->toBe('Test Loyalty Program');
        });

        it('can create a program', function () {
            $programData = [
                'name' => 'New Loyalty Program',
                'description' => 'Test program description',
                'program_type' => 'loyalty',
                'account_id' => 1,
                'status' => 'active',
                'settings' => [
                    'points_per_dollar' => 1,
                    'welcome_points' => 100
                ]
            ];

            $response = $this->postJson('/api/wallet/programs', $programData, [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(201)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.program.name'))->toBe('New Loyalty Program')
                ->and($response->json('data.message'))->toBe('Loyalty program created successfully');

            $this->assertDatabaseHas('passkit_programs', [
                'name' => 'New Loyalty Program',
                'account_id' => 1
            ]);
        });

        it('can get a specific program', function () {
            $program = createTestProgram([
                'name' => 'Specific Program',
                'account_id' => 1
            ]);

            $response = $this->getJson("/api/wallet/programs/{$program->id}", [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.program.name'))->toBe('Specific Program')
                ->and($response->json('data.statistics'))->toHaveKeys([
                    'total_members', 'active_members', 'total_transactions', 'total_points_issued'
                ]);
        });

        it('can update a program', function () {
            $program = createTestProgram([
                'name' => 'Original Name',
                'account_id' => 1
            ]);

            $updateData = [
                'name' => 'Updated Program Name',
                'description' => 'Updated description',
                'program_type' => 'loyalty',
                'account_id' => 1,
                'status' => 'active'
            ];

            $response = $this->putJson("/api/wallet/programs/{$program->id}", $updateData, [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.program.name'))->toBe('Updated Program Name')
                ->and($response->json('data.message'))->toBe('Loyalty program updated successfully');
        });

        it('can delete a program', function () {
            $program = createTestProgram([
                'name' => 'Program to Delete',
                'account_id' => 1
            ]);

            $response = $this->deleteJson("/api/wallet/programs/{$program->id}", [], [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.message'))->toBe('Loyalty program deleted successfully');

            $this->assertDatabaseMissing('passkit_programs', [
                'id' => $program->id
            ]);
        });

        it('validates program creation data', function () {
            $invalidData = [
                'name' => '', // Required field empty
                'program_type' => 'invalid_type',
                'account_id' => 999 // Non-existent account
            ];

            $response = $this->postJson('/api/wallet/programs', $invalidData, [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(422)
                ->and($response->json('success'))->toBeFalse()
                ->and($response->json('errors'))->toHaveKeys(['name', 'program_type', 'account_id']);
        });
    });

    describe('Members API', function () {
        it('can list members', function () {
            $member = createTestMember([
                'email' => 'test@example.com',
                'account_id' => 1
            ]);

            $response = $this->getJson('/api/wallet/members', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.members'))->toHaveCount(1)
                ->and($response->json('data.members.0.email'))->toBe('test@example.com');
        });

        it('can create a member', function () {
            $program = createTestProgram(['account_id' => 1]);

            $memberData = [
                'external_id' => 'customer_123',
                'program_id' => $program->id,
                'tier_id' => 'bronze',
                'account_id' => 1,
                'email' => 'newmember@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'points_balance' => 100,
                'preferences' => [
                    'language' => 'en',
                    'notifications' => true
                ],
                'tags' => ['new', 'vip']
            ];

            $response = $this->postJson('/api/wallet/members', $memberData, [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(201)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.member.email'))->toBe('newmember@example.com')
                ->and($response->json('data.member.points_balance'))->toBe(100)
                ->and($response->json('data.message'))->toBe('Member created successfully');

            $this->assertDatabaseHas('passkit_members', [
                'external_id' => 'customer_123',
                'email' => 'newmember@example.com',
                'account_id' => 1
            ]);
        });

        it('can get a specific member', function () {
            $member = createTestMember([
                'email' => 'specific@example.com',
                'account_id' => 1
            ]);

            $response = $this->getJson("/api/wallet/members/{$member->id}", [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.member.email'))->toBe('specific@example.com')
                ->and($response->json('data.statistics'))->toHaveKeys([
                    'lifetime_points', 'current_balance', 'total_transactions', 'last_activity', 'wallet_passes_count'
                ]);
        });

        it('can update a member', function () {
            $member = createTestMember([
                'first_name' => 'Original',
                'account_id' => 1
            ]);

            $updateData = [
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'phone' => '+1234567890',
                'preferences' => [
                    'language' => 'es',
                    'marketing' => false
                ]
            ];

            $response = $this->putJson("/api/wallet/members/{$member->id}", $updateData, [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.member.first_name'))->toBe('Updated')
                ->and($response->json('data.message'))->toBe('Member updated successfully');
        });

        it('can update member points', function () {
            $member = createTestMember([
                'points_balance' => 100,
                'account_id' => 1
            ]);

            $pointsData = [
                'points' => 50,
                'description' => 'Purchase reward',
                'reference_id' => 'order_123'
            ];

            $response = $this->postJson("/api/wallet/members/{$member->id}/points", $pointsData, [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.member.points_balance'))->toBe(150)
                ->and($response->json('data.transaction.points_amount'))->toBe(50)
                ->and($response->json('data.message'))->toBe('Points updated successfully');

            $this->assertDatabaseHas('passkit_transactions', [
                'member_id' => $member->id,
                'points_amount' => 50,
                'reference_id' => 'order_123'
            ]);
        });

        it('can filter members', function () {
            $program = createTestProgram(['account_id' => 1]);
            
            createTestMember([
                'email' => 'active@example.com',
                'status' => 'active',
                'program_id' => $program->id,
                'account_id' => 1
            ]);
            
            createTestMember([
                'email' => 'inactive@example.com',
                'status' => 'inactive',
                'program_id' => $program->id,
                'account_id' => 1
            ]);

            $response = $this->getJson('/api/wallet/members?status=active', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('data.members'))->toHaveCount(1)
                ->and($response->json('data.members.0.email'))->toBe('active@example.com');
        });
    });

    describe('Transactions API', function () {
        it('can list transactions', function () {
            $member = createTestMember(['account_id' => 1]);
            $transaction = createTestTransaction([
                'member_id' => $member->id,
                'description' => 'Test transaction'
            ]);

            $response = $this->getJson('/api/wallet/transactions', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.transactions'))->toHaveCount(1)
                ->and($response->json('data.transactions.0.description'))->toBe('Test transaction');
        });

        it('can create a transaction', function () {
            $member = createTestMember(['account_id' => 1]);

            $transactionData = [
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'account_id' => 1,
                'transaction_type' => 'earn',
                'points_amount' => 75,
                'description' => 'API created transaction',
                'reference_id' => 'api_ref_123',
                'purchase_amount' => 25.50,
                'purchase_currency' => 'USD',
                'process_immediately' => true
            ];

            $response = $this->postJson('/api/wallet/transactions', $transactionData, [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(201)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.transaction.points_amount'))->toBe(75)
                ->and($response->json('data.transaction.description'))->toBe('API created transaction')
                ->and($response->json('data.message'))->toBe('Transaction created successfully');

            $this->assertDatabaseHas('passkit_transactions', [
                'member_id' => $member->id,
                'points_amount' => 75,
                'reference_id' => 'api_ref_123'
            ]);
        });

        it('can filter transactions by date range', function () {
            $member = createTestMember(['account_id' => 1]);
            
            // Create transaction from yesterday
            createTestTransaction([
                'member_id' => $member->id,
                'processed_at' => now()->subDay(),
                'description' => 'Yesterday transaction'
            ]);
            
            // Create transaction from today
            createTestTransaction([
                'member_id' => $member->id,
                'processed_at' => now(),
                'description' => 'Today transaction'
            ]);

            $response = $this->getJson('/api/wallet/transactions?date_from=' . now()->toDateString(), [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('data.transactions'))->toHaveCount(1)
                ->and($response->json('data.transactions.0.description'))->toBe('Today transaction');
        });
    });

    describe('Wallet Passes API', function () {
        it('can list wallet passes', function () {
            $pass = createTestWalletPass([
                'user_id' => 1,
                'account_id' => 1
            ]);

            $response = $this->getJson('/api/wallet/passes', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.wallet_passes'))->toHaveCount(1);
        });

        it('can create a wallet pass', function () {
            $member = createTestMember(['account_id' => 1]);

            $passData = [
                'member_id' => $member->passkit_id,
                'user_id' => 1,
                'account_id' => 1,
                'pass_type' => 'loyalty',
                'pass_data' => [
                    'tier' => 'Gold',
                    'special_offers' => true
                ]
            ];

            $response = $this->postJson('/api/wallet/passes', $passData, [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(201)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.wallet_pass'))->toHaveKey('id')
                ->and($response->json('data.message'))->toBe('Wallet pass created successfully');
        });

        it('can filter wallet passes by installation status', function () {
            createTestWalletPass([
                'user_id' => 1,
                'account_id' => 1,
                'is_installed' => true
            ]);
            
            createTestWalletPass([
                'user_id' => 1,
                'account_id' => 1,
                'is_installed' => false
            ]);

            $response = $this->getJson('/api/wallet/passes?is_installed=true', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('data.wallet_passes'))->toHaveCount(1)
                ->and($response->json('data.wallet_passes.0.is_installed'))->toBeTrue();
        });
    });

    describe('Sync API', function () {
        it('can get sync status', function () {
            $response = $this->getJson('/api/wallet/sync/status?account_id=1', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.sync_status'))->toHaveKeys([
                    'pending_members', 'pending_wallet_passes', 'last_sync_at', 'sync_healthy'
                ]);
        });

        it('can trigger sync', function () {
            $syncData = [
                'account_id' => 1,
                'entity_type' => 'members',
                'force' => true
            ];

            $response = $this->postJson('/api/wallet/sync/trigger', $syncData, [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.sync_results'))->toHaveKey('members')
                ->and($response->json('data.message'))->toBe('Sync completed successfully');
        });
    });

    describe('Analytics API', function () {
        it('can get analytics', function () {
            // Create test data
            $program = createTestProgram(['account_id' => 1]);
            $member = createTestMember(['account_id' => 1, 'program_id' => $program->id]);
            createTestTransaction(['member_id' => $member->id]);
            createTestWalletPass(['user_id' => 1, 'account_id' => 1]);

            $response = $this->getJson('/api/wallet/analytics?account_id=1', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('success'))->toBeTrue()
                ->and($response->json('data.analytics'))->toHaveKeys([
                    'members', 'transactions', 'wallet_passes', 'points'
                ])
                ->and($response->json('data.analytics.members.total'))->toBe(1)
                ->and($response->json('data.analytics.transactions.total'))->toBe(1)
                ->and($response->json('data.analytics.wallet_passes.total'))->toBe(1);
        });

        it('can filter analytics by date range', function () {
            $program = createTestProgram(['account_id' => 1]);
            $member = createTestMember([
                'account_id' => 1, 
                'program_id' => $program->id,
                'enrolled_at' => now()->subDays(2)
            ]);

            $response = $this->getJson('/api/wallet/analytics?account_id=1&date_from=' . now()->subDay()->toDateString(), [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('data.analytics.members.new_this_period'))->toBe(0);
        });

        it('can filter analytics by metrics', function () {
            $response = $this->getJson('/api/wallet/analytics?account_id=1&metrics[]=members&metrics[]=points', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('data.analytics'))->toHaveKeys(['members', 'points'])
                ->and($response->json('data.analytics'))->not->toHaveKey('transactions');
        });
    });

    describe('Rate Limiting', function () {
        it('enforces rate limits', function () {
            // Set very low rate limit for testing
            Config::set('passkit.api_keys.0.rate_limit', 1);

            // First request should succeed
            $response1 = $this->getJson('/api/wallet/programs', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);
            expect($response1->status())->toBe(200);

            // Second request should be rate limited
            $response2 = $this->getJson('/api/wallet/programs', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);
            
            expect($response2->status())->toBe(429)
                ->and($response2->json('success'))->toBeFalse()
                ->and($response2->json('message'))->toBe('Rate limit exceeded');
        });
    });

    describe('Pagination', function () {
        it('paginates results correctly', function () {
            // Create multiple programs
            for ($i = 1; $i <= 25; $i++) {
                createTestProgram([
                    'name' => "Program {$i}",
                    'account_id' => 1
                ]);
            }

            $response = $this->getJson('/api/wallet/programs?per_page=10&page=2', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(200)
                ->and($response->json('data.programs'))->toHaveCount(10)
                ->and($response->json('data.meta.current_page'))->toBe(2)
                ->and($response->json('data.meta.total_count'))->toBe(25)
                ->and($response->json('data.meta.total_pages'))->toBe(3);
        });

        it('respects per_page limits', function () {
            $response = $this->getJson('/api/wallet/programs?per_page=150', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(422)
                ->and($response->json('errors.per_page'))->toContain('The per page field must not be greater than 100.');
        });
    });

    describe('Error Handling', function () {
        it('handles resource not found errors', function () {
            $response = $this->getJson('/api/wallet/programs/99999', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(404);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/wallet/programs', [], [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->status())->toBe(422)
                ->and($response->json('success'))->toBeFalse()
                ->and($response->json('errors'))->toHaveKeys(['name', 'program_type', 'account_id']);
        });

        it('returns consistent error format', function () {
            $response = $this->getJson('/api/wallet/programs/99999', [
                'Authorization' => 'Bearer test_api_key_123'
            ]);

            expect($response->json())->toHaveKeys(['success', 'message', 'timestamp'])
                ->and($response->json('success'))->toBeFalse()
                ->and($response->json('timestamp'))->toBeString();
        });
    });
});