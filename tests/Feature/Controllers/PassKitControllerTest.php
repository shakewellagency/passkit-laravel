<?php

use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use ShakewellAgency\PassKitLaravel\Services\PassKitService;
use Mockery\MockInterface;

describe('PassKitController Feature Tests', function () {
    beforeEach(function () {
        $this->mockPassKitService = Mockery::mock(PassKitService::class);
        $this->app->instance(PassKitService::class, $this->mockPassKitService);
    });

    afterEach(function () {
        Mockery::close();
    });

    describe('Program Management API', function () {
        it('can list programs', function () {
            createTestProgram(['account_id' => 1, 'name' => 'VIP Program']);
            createTestProgram(['account_id' => 1, 'name' => 'Regular Program']);

            $response = $this->getJson('/api/passkit/programs?account_id=1');

            $response->assertStatus(200)
                ->assertJsonCount(2, 'data')
                ->assertJsonFragment(['name' => 'VIP Program']);
        });

        it('can create program', function () {
            $programData = [
                'name' => 'New Program',
                'description' => 'A test program',
                'program_type' => 'membership',
                'account_id' => 1,
            ];

            $this->mockPassKitService->shouldReceive('createMembershipProgram')
                ->once()
                ->andReturn(['id' => 'passkit_program_123']);

            $response = $this->postJson('/api/passkit/programs', $programData);

            $response->assertStatus(201)
                ->assertJsonFragment(['name' => 'New Program'])
                ->assertJsonHas('data.passkit_id');
        });

        it('can show program', function () {
            $program = createTestProgram(['account_id' => 1]);

            $response = $this->getJson("/api/passkit/programs/{$program->id}");

            $response->assertStatus(200)
                ->assertJsonFragment(['id' => $program->id]);
        });

        it('can update program', function () {
            $program = createTestProgram(['name' => 'Old Name']);

            $updateData = ['name' => 'New Name'];

            $response = $this->patchJson("/api/passkit/programs/{$program->id}", $updateData);

            $response->assertStatus(200)
                ->assertJsonFragment(['name' => 'New Name']);
        });

        it('can delete program', function () {
            $program = createTestProgram();

            $response = $this->deleteJson("/api/passkit/programs/{$program->id}");

            $response->assertStatus(204);
            expect(PassKitProgram::find($program->id))->toBeNull();
        });

        it('validates program creation data', function () {
            $invalidData = [
                'name' => '', // Required field empty
                'program_type' => 'invalid_type',
            ];

            $response = $this->postJson('/api/passkit/programs', $invalidData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'program_type']);
        });
    });

    describe('Member Management API', function () {
        it('can list members', function () {
            $program = createTestProgram(['account_id' => 1]);
            createTestMember(['account_id' => 1, 'program_id' => $program->id]);
            createTestMember(['account_id' => 1, 'program_id' => $program->id]);

            $response = $this->getJson('/api/passkit/members?account_id=1');

            $response->assertStatus(200)
                ->assertJsonCount(2, 'data');
        });

        it('can create member', function () {
            $program = createTestProgram(['account_id' => 1]);
            
            $memberData = [
                'external_id' => 'user_123',
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'program_id' => $program->id,
                'account_id' => 1,
            ];

            $this->mockPassKitService->shouldReceive('enrollMember')
                ->once()
                ->andReturn(['id' => 'passkit_member_456']);

            $response = $this->postJson('/api/passkit/members', $memberData);

            $response->assertStatus(201)
                ->assertJsonFragment(['email' => 'test@example.com'])
                ->assertJsonHas('data.passkit_id');
        });

        it('can show member', function () {
            $member = createTestMember(['account_id' => 1]);

            $response = $this->getJson("/api/passkit/members/{$member->id}");

            $response->assertStatus(200)
                ->assertJsonFragment(['id' => $member->id]);
        });

        it('can update member', function () {
            $member = createTestMember(['email' => 'old@example.com']);

            $updateData = ['email' => 'new@example.com'];

            $response = $this->patchJson("/api/passkit/members/{$member->id}", $updateData);

            $response->assertStatus(200)
                ->assertJsonFragment(['email' => 'new@example.com']);
        });

        it('can update member points', function () {
            $member = createTestMember(['points_balance' => 100]);

            $this->mockPassKitService->shouldReceive('updateMemberPoints')
                ->once()
                ->andReturn(['id' => $member->passkit_id]);

            $pointsData = [
                'points' => 50,
                'description' => 'Bonus points',
            ];

            $response = $this->postJson("/api/passkit/members/{$member->id}/points", $pointsData);

            $response->assertStatus(200)
                ->assertJsonFragment(['success' => true]);
        });

        it('can delete member', function () {
            $member = createTestMember();

            $this->mockPassKitService->shouldReceive('deleteMember')
                ->once()
                ->andReturn(['success' => true]);

            $response = $this->deleteJson("/api/passkit/members/{$member->id}");

            $response->assertStatus(204);
        });

        it('can search members', function () {
            createTestMember([
                'account_id' => 1,
                'email' => 'john@example.com',
                'first_name' => 'John',
            ]);
            createTestMember([
                'account_id' => 1,
                'email' => 'jane@example.com',
                'first_name' => 'Jane',
            ]);

            $response = $this->getJson('/api/passkit/members/search?account_id=1&q=john');

            $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonFragment(['email' => 'john@example.com']);
        });

        it('validates member creation data', function () {
            $invalidData = [
                'email' => 'invalid-email',
                'external_id' => '', // Required
            ];

            $response = $this->postJson('/api/passkit/members', $invalidData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'external_id']);
        });
    });

    describe('Transaction Management API', function () {
        it('can list transactions', function () {
            $member = createTestMember(['account_id' => 1]);
            createTestTransaction(['member_id' => $member->id]);
            createTestTransaction(['member_id' => $member->id]);

            $response = $this->getJson("/api/passkit/members/{$member->id}/transactions");

            $response->assertStatus(200)
                ->assertJsonCount(2, 'data');
        });

        it('can create transaction', function () {
            $member = createTestMember();

            $transactionData = [
                'transaction_type' => 'earn',
                'points_amount' => 100,
                'description' => 'Purchase reward',
            ];

            $response = $this->postJson("/api/passkit/members/{$member->id}/transactions", $transactionData);

            $response->assertStatus(201)
                ->assertJsonFragment(['transaction_type' => 'earn']);
        });

        it('can show transaction', function () {
            $transaction = createTestTransaction();

            $response = $this->getJson("/api/passkit/transactions/{$transaction->id}");

            $response->assertStatus(200)
                ->assertJsonFragment(['id' => $transaction->id]);
        });

        it('can update transaction status', function () {
            $transaction = createTestTransaction(['status' => 'pending']);

            $statusData = ['status' => 'completed'];

            $response = $this->patchJson("/api/passkit/transactions/{$transaction->id}/status", $statusData);

            $response->assertStatus(200)
                ->assertJsonFragment(['status' => 'completed']);
        });

        it('can get transaction summary for member', function () {
            $member = createTestMember();
            
            createTestTransaction([
                'member_id' => $member->id,
                'transaction_type' => 'earn',
                'points_amount' => 100,
            ]);
            createTestTransaction([
                'member_id' => $member->id,
                'transaction_type' => 'burn',
                'points_amount' => -50,
            ]);

            $response = $this->getJson("/api/passkit/members/{$member->id}/transactions/summary");

            $response->assertStatus(200)
                ->assertJsonHas('data.total_earned')
                ->assertJsonHas('data.total_spent');
        });
    });

    describe('Wallet Pass Management API', function () {
        it('can list wallet passes', function () {
            createTestWalletPass(['user_id' => 1, 'account_id' => 1]);
            createTestWalletPass(['user_id' => 1, 'account_id' => 1]);

            $response = $this->getJson('/api/passkit/wallet-passes?user_id=1&account_id=1');

            $response->assertStatus(200)
                ->assertJsonCount(2, 'data');
        });

        it('can create wallet pass', function () {
            $template = createTestCardTemplate();
            $member = createTestMember();

            $passData = [
                'user_id' => 1,
                'account_id' => 1,
                'template_id' => $template->id,
                'member_passkit_id' => $member->passkit_id,
                'pass_data' => ['points' => 100],
            ];

            $this->mockPassKitService->shouldReceive('createPass')
                ->once()
                ->andReturn(['id' => 'passkit_pass_123']);

            $response = $this->postJson('/api/passkit/wallet-passes', $passData);

            $response->assertStatus(201)
                ->assertJsonHas('data.passkit_id');
        });

        it('can show wallet pass', function () {
            $pass = createTestWalletPass();

            $response = $this->getJson("/api/passkit/wallet-passes/{$pass->id}");

            $response->assertStatus(200)
                ->assertJsonFragment(['id' => $pass->id]);
        });

        it('can update wallet pass data', function () {
            $pass = createTestWalletPass(['pass_data' => ['points' => 100]]);

            $updateData = ['pass_data' => ['points' => 200]];

            $response = $this->patchJson("/api/passkit/wallet-passes/{$pass->id}", $updateData);

            $response->assertStatus(200)
                ->assertJsonPath('data.pass_data.points', 200);
        });

        it('can get pass installation URLs', function () {
            $pass = createTestWalletPass();

            $this->mockPassKitService->shouldReceive('getPassInstallationPackage')
                ->once()
                ->andReturn([
                    'urls' => [
                        'apple' => 'https://wallet.apple.com/test',
                        'google' => 'https://pay.google.com/test',
                    ],
                ]);

            $response = $this->getJson("/api/passkit/wallet-passes/{$pass->id}/install-urls");

            $response->assertStatus(200)
                ->assertJsonHas('data.urls.apple')
                ->assertJsonHas('data.urls.google');
        });

        it('can record pass installation', function () {
            $pass = createTestWalletPass(['is_installed' => false]);

            $installData = [
                'device_type' => 'ios',
                'device_model' => 'iPhone 15 Pro',
            ];

            $response = $this->postJson("/api/passkit/wallet-passes/{$pass->id}/install", $installData);

            $response->assertStatus(200)
                ->assertJsonFragment(['is_installed' => true]);
        });
    });

    describe('Synchronization API', function () {
        it('can sync member from PassKit API', function () {
            $member = createTestMember(['passkit_id' => 'member_123']);

            $this->mockPassKitService->shouldReceive('getMember')
                ->once()
                ->andReturn([
                    'id' => 'member_123',
                    'points' => 250,
                    'tier' => 'gold',
                ]);

            $response = $this->postJson("/api/passkit/members/{$member->id}/sync");

            $response->assertStatus(200)
                ->assertJsonFragment(['success' => true]);
        });

        it('can perform full account sync', function () {
            createTestMember(['account_id' => 1, 'passkit_id' => 'member_1']);
            createTestWalletPass(['account_id' => 1, 'passkit_id' => 'pass_1']);

            $this->mockPassKitService->shouldReceive('getMember')
                ->once()
                ->andReturn(['id' => 'member_1', 'points' => 100]);

            $this->mockPassKitService->shouldReceive('getMemberTransactions')
                ->once()
                ->andReturn([]);

            $this->mockPassKitService->shouldReceive('getPass')
                ->once()
                ->andReturn(['id' => 'pass_1', 'status' => 'active']);

            $response = $this->postJson('/api/passkit/sync/full', ['account_id' => 1]);

            $response->assertStatus(200)
                ->assertJsonHas('data.members.synced')
                ->assertJsonHas('data.passes.synced');
        });

        it('can get sync status', function () {
            createTestMember(['account_id' => 1, 'sync_pending' => true]);
            createTestWalletPass(['account_id' => 1, 'sync_pending' => false]);

            $response = $this->getJson('/api/passkit/sync/status?account_id=1');

            $response->assertStatus(200)
                ->assertJsonHas('data.members_pending_sync')
                ->assertJsonHas('data.passes_pending_sync');
        });
    });

    describe('Analytics and Reporting API', function () {
        it('can get member statistics', function () {
            createTestMember(['account_id' => 1, 'status' => 'active']);
            createTestMember(['account_id' => 1, 'status' => 'active']);
            createTestMember(['account_id' => 1, 'status' => 'inactive']);

            $response = $this->getJson('/api/passkit/analytics/members?account_id=1');

            $response->assertStatus(200)
                ->assertJsonFragment(['total' => 3, 'active' => 2, 'inactive' => 1]);
        });

        it('can get transaction statistics', function () {
            $member = createTestMember(['account_id' => 1]);
            
            createTestTransaction([
                'member_id' => $member->id,
                'transaction_type' => 'earn',
                'points_amount' => 100,
            ]);
            createTestTransaction([
                'member_id' => $member->id,
                'transaction_type' => 'burn',
                'points_amount' => -50,
            ]);

            $response = $this->getJson('/api/passkit/analytics/transactions?account_id=1');

            $response->assertStatus(200)
                ->assertJsonHas('data.total_transactions')
                ->assertJsonHas('data.total_earned')
                ->assertJsonHas('data.total_spent');
        });

        it('can get performance summary', function () {
            createTestAuditLog([
                'account_id' => 1,
                'event_type' => 'api_call',
                'execution_time_ms' => 150,
                'status' => 'success',
            ]);
            createTestAuditLog([
                'account_id' => 1,
                'event_type' => 'api_call',
                'execution_time_ms' => 250,
                'status' => 'success',
            ]);

            $response = $this->getJson('/api/passkit/analytics/performance?account_id=1');

            $response->assertStatus(200)
                ->assertJsonHas('data.avg_execution_time')
                ->assertJsonHas('data.success_rate');
        });
    });

    describe('Error Handling', function () {
        it('returns 404 for non-existent program', function () {
            $response = $this->getJson('/api/passkit/programs/999999');

            $response->assertStatus(404)
                ->assertJsonFragment(['message' => 'Program not found']);
        });

        it('returns 404 for non-existent member', function () {
            $response = $this->getJson('/api/passkit/members/999999');

            $response->assertStatus(404)
                ->assertJsonFragment(['message' => 'Member not found']);
        });

        it('handles PassKit API errors gracefully', function () {
            $program = createTestProgram();

            $this->mockPassKitService->shouldReceive('createMembershipProgram')
                ->once()
                ->andThrow(new \Exception('PassKit API Error'));

            $response = $this->postJson('/api/passkit/programs', [
                'name' => 'Test Program',
                'account_id' => 1,
            ]);

            $response->assertStatus(500)
                ->assertJsonFragment(['message' => 'PassKit service error']);
        });

        it('validates required account_id parameter', function () {
            $response = $this->getJson('/api/passkit/programs');

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['account_id']);
        });

        it('returns 403 for unauthorized account access', function () {
            $program = createTestProgram(['account_id' => 999]);

            $response = $this->getJson("/api/passkit/programs/{$program->id}");

            $response->assertStatus(403)
                ->assertJsonFragment(['message' => 'Unauthorized']);
        });
    });

    describe('Rate Limiting', function () {
        it('applies rate limiting to API endpoints', function () {
            $program = createTestProgram(['account_id' => 1]);

            // Make multiple requests quickly
            for ($i = 0; $i < 65; $i++) {
                $response = $this->getJson("/api/passkit/programs/{$program->id}");
                
                if ($i < 60) {
                    $response->assertStatus(200);
                } else {
                    // Should be rate limited after 60 requests
                    $response->assertStatus(429);
                    break;
                }
            }
        });
    });

    describe('API Versioning', function () {
        it('supports API versioning through headers', function () {
            $program = createTestProgram(['account_id' => 1]);

            $response = $this->withHeaders([
                'Accept' => 'application/vnd.passkit.v1+json',
            ])->getJson("/api/passkit/programs/{$program->id}");

            $response->assertStatus(200)
                ->assertHeader('API-Version', 'v1');
        });

        it('defaults to latest version without header', function () {
            $program = createTestProgram(['account_id' => 1]);

            $response = $this->getJson("/api/passkit/programs/{$program->id}");

            $response->assertStatus(200)
                ->assertHeader('API-Version', 'v1');
        });
    });
});