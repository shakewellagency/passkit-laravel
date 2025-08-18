<?php

use ShakewellAgency\PassKitLaravel\Services\PassKitCrudManager;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use Mockery\MockInterface;

describe('PassKitCrudManager', function () {
    beforeEach(function () {
        $this->manager = app(PassKitCrudManager::class);
    });

    afterEach(function () {
        Mockery::close();
    });

    it('can instantiate the manager', function () {
        expect($this->manager)->toBeInstanceOf(PassKitCrudManager::class);
    });

    describe('Program Management', function () {
        it('can create program', function () {
            $programData = [
                'name' => 'Test Program',
                'description' => 'A test program',
                'program_type' => 'membership',
                'account_id' => 1,
            ];

            $program = $this->manager->createProgram($programData);

            expect($program)->toBeInstanceOf(PassKitProgram::class)
                ->and($program->name)->toBe('Test Program')
                ->and($program->program_type)->toBe('membership')
                ->and($program->account_id)->toBe(1);
        });

        it('can update program', function () {
            $program = createTestProgram([
                'name' => 'Original Name',
                'description' => 'Original Description',
            ]);

            $updatedProgram = $this->manager->updateProgram($program->id, [
                'name' => 'Updated Name',
                'description' => 'Updated Description',
            ]);

            expect($updatedProgram->name)->toBe('Updated Name')
                ->and($updatedProgram->description)->toBe('Updated Description');
        });

        it('can delete program', function () {
            $program = createTestProgram();

            $result = $this->manager->deleteProgram($program->id);

            expect($result)->toBeTrue();
            expect(PassKitProgram::find($program->id))->toBeNull();
        });

        it('can get program by id', function () {
            $program = createTestProgram();

            $found = $this->manager->getProgram($program->id);

            expect($found)->toBeInstanceOf(PassKitProgram::class)
                ->and($found->id)->toBe($program->id);
        });

        it('returns null for non-existent program', function () {
            $found = $this->manager->getProgram(999999);

            expect($found)->toBeNull();
        });

        it('can list programs', function () {
            createTestProgram(['account_id' => 1]);
            createTestProgram(['account_id' => 1]);
            createTestProgram(['account_id' => 2]);

            $programs = $this->manager->listPrograms(1);

            expect($programs)->toHaveCount(2);
        });

        it('can list programs with pagination', function () {
            for ($i = 0; $i < 15; $i++) {
                createTestProgram(['account_id' => 1]);
            }

            $paginatedPrograms = $this->manager->listPrograms(1, ['per_page' => 10]);

            expect($paginatedPrograms->count())->toBe(10)
                ->and($paginatedPrograms->total())->toBe(15);
        });
    });

    describe('Member Management', function () {
        it('can create member', function () {
            $memberData = [
                'passkit_id' => 'test_member_123',
                'external_id' => 'user_456',
                'email' => 'test@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'account_id' => 1,
            ];

            $member = $this->manager->createMember($memberData);

            expect($member)->toBeInstanceOf(PassKitMember::class)
                ->and($member->passkit_id)->toBe('test_member_123')
                ->and($member->email)->toBe('test@example.com')
                ->and($member->full_name)->toBe('John Doe');
        });

        it('can update member', function () {
            $member = createTestMember([
                'email' => 'old@example.com',
                'points_balance' => 100,
            ]);

            $updatedMember = $this->manager->updateMember($member->id, [
                'email' => 'new@example.com',
                'points_balance' => 250,
            ]);

            expect($updatedMember->email)->toBe('new@example.com')
                ->and($updatedMember->points_balance)->toBe(250);
        });

        it('can delete member', function () {
            $member = createTestMember();

            $result = $this->manager->deleteMember($member->id);

            expect($result)->toBeTrue();
            expect(PassKitMember::find($member->id))->toBeNull();
        });

        it('can get member by id', function () {
            $member = createTestMember();

            $found = $this->manager->getMember($member->id);

            expect($found)->toBeInstanceOf(PassKitMember::class)
                ->and($found->id)->toBe($member->id);
        });

        it('can find member by passkit_id', function () {
            $member = createTestMember(['passkit_id' => 'unique_passkit_123']);

            $found = $this->manager->findMemberByPassKitId('unique_passkit_123');

            expect($found)->toBeInstanceOf(PassKitMember::class)
                ->and($found->passkit_id)->toBe('unique_passkit_123');
        });

        it('can find member by external_id', function () {
            $member = createTestMember([
                'external_id' => 'user_external_456',
                'account_id' => 1,
            ]);

            $found = $this->manager->findMemberByExternalId('user_external_456', 1);

            expect($found)->toBeInstanceOf(PassKitMember::class)
                ->and($found->external_id)->toBe('user_external_456');
        });

        it('can list members', function () {
            createTestMember(['account_id' => 1]);
            createTestMember(['account_id' => 1]);
            createTestMember(['account_id' => 2]);

            $members = $this->manager->listMembers(1);

            expect($members)->toHaveCount(2);
        });

        it('can filter members by status', function () {
            createTestMember(['account_id' => 1, 'status' => 'active']);
            createTestMember(['account_id' => 1, 'status' => 'inactive']);

            $activeMembers = $this->manager->listMembers(1, ['status' => 'active']);

            expect($activeMembers)->toHaveCount(1)
                ->and($activeMembers->first()->status)->toBe('active');
        });

        it('can filter members by program', function () {
            $program1 = createTestProgram();
            $program2 = createTestProgram();

            createTestMember(['account_id' => 1, 'program_id' => $program1->id]);
            createTestMember(['account_id' => 1, 'program_id' => $program2->id]);

            $programMembers = $this->manager->listMembers(1, ['program_id' => $program1->id]);

            expect($programMembers)->toHaveCount(1)
                ->and($programMembers->first()->program_id)->toBe($program1->id);
        });
    });

    describe('Transaction Management', function () {
        it('can create transaction', function () {
            $member = createTestMember();
            
            $transactionData = [
                'passkit_transaction_id' => 'txn_123',
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
                'transaction_type' => 'earn',
                'points_amount' => 100,
                'description' => 'Purchase reward',
            ];

            $transaction = $this->manager->createTransaction($transactionData);

            expect($transaction)->toBeInstanceOf(PassKitTransaction::class)
                ->and($transaction->passkit_transaction_id)->toBe('txn_123')
                ->and($transaction->transaction_type)->toBe('earn')
                ->and($transaction->points_amount)->toBe(100);
        });

        it('can update transaction', function () {
            $transaction = createTestTransaction([
                'status' => 'pending',
                'description' => 'Original description',
            ]);

            $updatedTransaction = $this->manager->updateTransaction($transaction->id, [
                'status' => 'completed',
                'description' => 'Updated description',
            ]);

            expect($updatedTransaction->status)->toBe('completed')
                ->and($updatedTransaction->description)->toBe('Updated description');
        });

        it('can delete transaction', function () {
            $transaction = createTestTransaction();

            $result = $this->manager->deleteTransaction($transaction->id);

            expect($result)->toBeTrue();
            expect(PassKitTransaction::find($transaction->id))->toBeNull();
        });

        it('can get transaction by id', function () {
            $transaction = createTestTransaction();

            $found = $this->manager->getTransaction($transaction->id);

            expect($found)->toBeInstanceOf(PassKitTransaction::class)
                ->and($found->id)->toBe($transaction->id);
        });

        it('can list transactions for member', function () {
            $member = createTestMember();
            
            createTestTransaction([
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
            ]);
            createTestTransaction([
                'member_id' => $member->id,
                'member_passkit_id' => $member->passkit_id,
            ]);

            $transactions = $this->manager->listTransactions([
                'member_id' => $member->id,
            ]);

            expect($transactions)->toHaveCount(2);
        });

        it('can filter transactions by type', function () {
            $member = createTestMember();
            
            createTestTransaction([
                'member_id' => $member->id,
                'transaction_type' => 'earn',
            ]);
            createTestTransaction([
                'member_id' => $member->id,
                'transaction_type' => 'burn',
            ]);

            $earnTransactions = $this->manager->listTransactions([
                'member_id' => $member->id,
                'transaction_type' => 'earn',
            ]);

            expect($earnTransactions)->toHaveCount(1)
                ->and($earnTransactions->first()->transaction_type)->toBe('earn');
        });
    });

    describe('Wallet Pass Management', function () {
        it('can create wallet pass', function () {
            $template = createTestCardTemplate();
            
            $passData = [
                'passkit_id' => 'pass_123',
                'user_id' => 1,
                'account_id' => 1,
                'template_id' => $template->id,
                'pass_data' => ['points' => 100],
                'status' => 'active',
            ];

            $pass = $this->manager->createWalletPass($passData);

            expect($pass)->toBeInstanceOf(WalletPass::class)
                ->and($pass->passkit_id)->toBe('pass_123')
                ->and($pass->user_id)->toBe(1)
                ->and($pass->pass_data['points'])->toBe(100);
        });

        it('can update wallet pass', function () {
            $pass = createTestWalletPass([
                'status' => 'inactive',
                'pass_data' => ['points' => 100],
            ]);

            $updatedPass = $this->manager->updateWalletPass($pass->id, [
                'status' => 'active',
                'pass_data' => ['points' => 200],
            ]);

            expect($updatedPass->status)->toBe('active')
                ->and($updatedPass->pass_data['points'])->toBe(200);
        });

        it('can delete wallet pass', function () {
            $pass = createTestWalletPass();

            $result = $this->manager->deleteWalletPass($pass->id);

            expect($result)->toBeTrue();
            expect(WalletPass::find($pass->id))->toBeNull();
        });

        it('can get wallet pass by id', function () {
            $pass = createTestWalletPass();

            $found = $this->manager->getWalletPass($pass->id);

            expect($found)->toBeInstanceOf(WalletPass::class)
                ->and($found->id)->toBe($pass->id);
        });

        it('can list wallet passes for user', function () {
            createTestWalletPass(['user_id' => 1, 'account_id' => 1]);
            createTestWalletPass(['user_id' => 1, 'account_id' => 1]);
            createTestWalletPass(['user_id' => 2, 'account_id' => 1]);

            $userPasses = $this->manager->listWalletPasses([
                'user_id' => 1,
                'account_id' => 1,
            ]);

            expect($userPasses)->toHaveCount(2);
        });

        it('can filter wallet passes by status', function () {
            createTestWalletPass(['user_id' => 1, 'status' => 'active']);
            createTestWalletPass(['user_id' => 1, 'status' => 'inactive']);

            $activePasses = $this->manager->listWalletPasses([
                'user_id' => 1,
                'status' => 'active',
            ]);

            expect($activePasses)->toHaveCount(1)
                ->and($activePasses->first()->status)->toBe('active');
        });
    });

    describe('Batch Operations', function () {
        it('can batch create members', function () {
            $membersData = [
                [
                    'passkit_id' => 'member_1',
                    'external_id' => 'user_1',
                    'email' => 'user1@example.com',
                    'account_id' => 1,
                ],
                [
                    'passkit_id' => 'member_2',
                    'external_id' => 'user_2',
                    'email' => 'user2@example.com',
                    'account_id' => 1,
                ],
            ];

            $members = $this->manager->batchCreateMembers($membersData);

            expect($members)->toHaveCount(2)
                ->and($members->first())->toBeInstanceOf(PassKitMember::class);
        });

        it('can batch update member points', function () {
            $member1 = createTestMember(['points_balance' => 100]);
            $member2 = createTestMember(['points_balance' => 200]);

            $updates = [
                ['id' => $member1->id, 'points_balance' => 150],
                ['id' => $member2->id, 'points_balance' => 250],
            ];

            $result = $this->manager->batchUpdateMemberPoints($updates);

            expect($result)->toBeTrue();
            
            $member1->refresh();
            $member2->refresh();
            
            expect($member1->points_balance)->toBe(150)
                ->and($member2->points_balance)->toBe(250);
        });

        it('can batch create transactions', function () {
            $member = createTestMember();
            
            $transactionsData = [
                [
                    'passkit_transaction_id' => 'txn_1',
                    'member_id' => $member->id,
                    'member_passkit_id' => $member->passkit_id,
                    'transaction_type' => 'earn',
                    'points_amount' => 50,
                ],
                [
                    'passkit_transaction_id' => 'txn_2',
                    'member_id' => $member->id,
                    'member_passkit_id' => $member->passkit_id,
                    'transaction_type' => 'earn',
                    'points_amount' => 75,
                ],
            ];

            $transactions = $this->manager->batchCreateTransactions($transactionsData);

            expect($transactions)->toHaveCount(2)
                ->and($transactions->first())->toBeInstanceOf(PassKitTransaction::class);
        });
    });

    describe('Search and Filtering', function () {
        it('can search members by email', function () {
            createTestMember(['email' => 'john@example.com', 'account_id' => 1]);
            createTestMember(['email' => 'jane@example.com', 'account_id' => 1]);

            $results = $this->manager->searchMembers('john@example.com', 1);

            expect($results)->toHaveCount(1)
                ->and($results->first()->email)->toBe('john@example.com');
        });

        it('can search members by name', function () {
            createTestMember(['first_name' => 'John', 'last_name' => 'Doe', 'account_id' => 1]);
            createTestMember(['first_name' => 'Jane', 'last_name' => 'Smith', 'account_id' => 1]);

            $results = $this->manager->searchMembers('John', 1);

            expect($results)->toHaveCount(1)
                ->and($results->first()->first_name)->toBe('John');
        });

        it('can get member statistics', function () {
            createTestMember(['account_id' => 1, 'status' => 'active']);
            createTestMember(['account_id' => 1, 'status' => 'active']);
            createTestMember(['account_id' => 1, 'status' => 'inactive']);

            $stats = $this->manager->getMemberStatistics(1);

            expect($stats)->toHaveKeys(['total', 'active', 'inactive'])
                ->and($stats['total'])->toBe(3)
                ->and($stats['active'])->toBe(2)
                ->and($stats['inactive'])->toBe(1);
        });

        it('can get transaction statistics', function () {
            $member = createTestMember(['account_id' => 1]);
            
            createTestTransaction([
                'member_id' => $member->id,
                'transaction_type' => 'earn',
                'points_amount' => 100,
                'status' => 'completed',
            ]);
            createTestTransaction([
                'member_id' => $member->id,
                'transaction_type' => 'burn',
                'points_amount' => -50,
                'status' => 'completed',
            ]);

            $stats = $this->manager->getTransactionStatistics(1);

            expect($stats)->toHaveKeys(['total_transactions', 'total_earned', 'total_spent'])
                ->and($stats['total_transactions'])->toBe(2)
                ->and($stats['total_earned'])->toBe(100)
                ->and($stats['total_spent'])->toBe(50);
        });
    });

    describe('Error Handling', function () {
        it('throws exception when creating member with invalid data', function () {
            $invalidData = [
                'email' => 'invalid-email',
                'points_balance' => 'not-a-number',
            ];

            expect(function () use ($invalidData) {
                $this->manager->createMember($invalidData);
            })->toThrow(\Exception::class);
        });

        it('returns null when getting non-existent member', function () {
            $result = $this->manager->getMember(999999);

            expect($result)->toBeNull();
        });

        it('returns false when deleting non-existent member', function () {
            $result = $this->manager->deleteMember(999999);

            expect($result)->toBeFalse();
        });
    });
});