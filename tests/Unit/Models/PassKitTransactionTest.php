<?php

use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;
use ShakewellAgency\PassKitLaravel\Models\PassKitMember;

describe('PassKitTransaction Model', function () {
    it('can create a transaction', function () {
        $transaction = createTestTransaction([
            'transaction_type' => 'earn',
            'points_amount' => 100,
            'description' => 'Purchase reward',
        ]);

        expect($transaction)->toBeInstanceOf(PassKitTransaction::class)
            ->and($transaction->transaction_type)->toBe('earn')
            ->and($transaction->points_amount)->toBe(100)
            ->and($transaction->description)->toBe('Purchase reward');
    });

    it('has required fillable attributes', function () {
        $transaction = new PassKitTransaction();
        $fillable = $transaction->getFillable();

        expect($fillable)->toContain('passkit_transaction_id')
            ->and($fillable)->toContain('member_passkit_id')
            ->and($fillable)->toContain('transaction_type')
            ->and($fillable)->toContain('points_amount')
            ->and($fillable)->toContain('points_balance_before')
            ->and($fillable)->toContain('points_balance_after')
            ->and($fillable)->toContain('description')
            ->and($fillable)->toContain('status');
    });

    it('casts dates correctly', function () {
        $transaction = createTestTransaction([
            'processed_at' => '2024-01-15 10:30:00',
            'expires_at' => '2024-12-31 23:59:59',
        ]);

        expect($transaction->processed_at)->toBeInstanceOf(Carbon\Carbon::class)
            ->and($transaction->expires_at)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('casts JSON fields to arrays', function () {
        $transaction = createTestTransaction([
            'webhook_data' => ['source' => 'mobile_app'],
            'passkit_data' => ['api_version' => '1.0'],
        ]);

        expect($transaction->webhook_data)->toBeArray()
            ->and($transaction->passkit_data)->toBeArray()
            ->and($transaction->webhook_data['source'])->toBe('mobile_app');
    });

    it('casts decimals correctly', function () {
        $transaction = createTestTransaction([
            'purchase_amount' => '25.99',
            'points_multiplier' => '1.5',
        ]);

        expect($transaction->purchase_amount)->toBeFloat()
            ->and($transaction->points_multiplier)->toBeFloat()
            ->and($transaction->purchase_amount)->toBe(25.99);
    });

    it('has member relationship', function () {
        $member = createTestMember();
        $transaction = createTestTransaction([
            'member_id' => $member->id,
            'member_passkit_id' => $member->passkit_id,
        ]);

        expect($transaction->member)->toBeInstanceOf(PassKitMember::class)
            ->and($transaction->member->id)->toBe($member->id);
    });

    it('can scope by member', function () {
        $member1 = createTestMember();
        $member2 = createTestMember();
        
        createTestTransaction(['member_passkit_id' => $member1->passkit_id]);
        createTestTransaction(['member_passkit_id' => $member2->passkit_id]);

        $transactions = PassKitTransaction::byMember($member1->passkit_id)->get();

        expect($transactions)->toHaveCount(1)
            ->and($transactions->first()->member_passkit_id)->toBe($member1->passkit_id);
    });

    it('can scope by transaction type', function () {
        createTestTransaction(['transaction_type' => 'earn']);
        createTestTransaction(['transaction_type' => 'burn']);

        $earnTransactions = PassKitTransaction::byType('earn')->get();

        expect($earnTransactions)->toHaveCount(1)
            ->and($earnTransactions->first()->transaction_type)->toBe('earn');
    });

    it('can scope by status', function () {
        createTestTransaction(['status' => 'completed']);
        createTestTransaction(['status' => 'pending']);

        $completedTransactions = PassKitTransaction::completed()->get();

        expect($completedTransactions)->toHaveCount(1)
            ->and($completedTransactions->first()->status)->toBe('completed');
    });

    it('can scope by date range', function () {
        createTestTransaction(['created_at' => now()->subDays(5)]);
        createTestTransaction(['created_at' => now()->subDays(15)]);

        $recentTransactions = PassKitTransaction::createdAfter(now()->subDays(10))->get();

        expect($recentTransactions)->toHaveCount(1);
    });

    it('can scope pending transactions', function () {
        createTestTransaction(['status' => 'pending']);
        createTestTransaction(['status' => 'completed']);

        $pendingTransactions = PassKitTransaction::pending()->get();

        expect($pendingTransactions)->toHaveCount(1)
            ->and($pendingTransactions->first()->status)->toBe('pending');
    });

    it('can scope failed transactions', function () {
        createTestTransaction(['status' => 'failed']);
        createTestTransaction(['status' => 'completed']);

        $failedTransactions = PassKitTransaction::failed()->get();

        expect($failedTransactions)->toHaveCount(1)
            ->and($failedTransactions->first()->status)->toBe('failed');
    });

    it('can scope by points amount range', function () {
        createTestTransaction(['points_amount' => 50]);
        createTestTransaction(['points_amount' => 150]);
        createTestTransaction(['points_amount' => 250]);

        $midRangeTransactions = PassKitTransaction::pointsBetween(100, 200)->get();

        expect($midRangeTransactions)->toHaveCount(1)
            ->and($midRangeTransactions->first()->points_amount)->toBe(150);
    });

    it('can scope expired transactions', function () {
        createTestTransaction([
            'expires_at' => now()->subDays(1),
            'is_expired' => true,
        ]);
        createTestTransaction([
            'expires_at' => now()->addDays(1),
            'is_expired' => false,
        ]);

        $expiredTransactions = PassKitTransaction::expired()->get();

        expect($expiredTransactions)->toHaveCount(1)
            ->and($expiredTransactions->first()->is_expired)->toBeTrue();
    });

    it('has is_earning accessor', function () {
        $earnTransaction = createTestTransaction(['transaction_type' => 'earn']);
        $burnTransaction = createTestTransaction(['transaction_type' => 'burn']);

        expect($earnTransaction->is_earning)->toBeTrue()
            ->and($burnTransaction->is_earning)->toBeFalse();
    });

    it('has is_spending accessor', function () {
        $earnTransaction = createTestTransaction(['transaction_type' => 'earn']);
        $burnTransaction = createTestTransaction(['transaction_type' => 'burn']);

        expect($earnTransaction->is_spending)->toBeFalse()
            ->and($burnTransaction->is_spending)->toBeTrue();
    });

    it('has is_completed accessor', function () {
        $completedTransaction = createTestTransaction(['status' => 'completed']);
        $pendingTransaction = createTestTransaction(['status' => 'pending']);

        expect($completedTransaction->is_completed)->toBeTrue()
            ->and($pendingTransaction->is_completed)->toBeFalse();
    });

    it('has is_reversed accessor', function () {
        $normalTransaction = createTestTransaction();
        $reversedTransaction = createTestTransaction(['reversed_by_transaction_id' => 'txn_123']);

        expect($normalTransaction->is_reversed)->toBeFalse()
            ->and($reversedTransaction->is_reversed)->toBeTrue();
    });

    it('can complete transaction', function () {
        $transaction = createTestTransaction(['status' => 'pending']);

        $transaction->complete();

        expect($transaction->status)->toBe('completed')
            ->and($transaction->processed_at)->not->toBeNull();
    });

    it('can fail transaction', function () {
        $transaction = createTestTransaction(['status' => 'pending']);

        $transaction->fail('Insufficient balance');

        expect($transaction->status)->toBe('failed')
            ->and($transaction->failure_reason)->toBe('Insufficient balance');
    });

    it('can cancel transaction', function () {
        $transaction = createTestTransaction(['status' => 'pending']);

        $transaction->cancel();

        expect($transaction->status)->toBe('cancelled');
    });

    it('can reverse transaction', function () {
        $transaction = createTestTransaction(['status' => 'completed']);

        $transaction->reverse('txn_reverse_123');

        expect($transaction->status)->toBe('reversed')
            ->and($transaction->reversed_by_transaction_id)->toBe('txn_reverse_123');
    });

    it('can expire transaction', function () {
        $transaction = createTestTransaction([
            'expires_at' => now()->addDays(1),
            'is_expired' => false,
        ]);

        $transaction->expire();

        expect($transaction->is_expired)->toBeTrue()
            ->and($transaction->expired_at)->not->toBeNull();
    });

    it('validates transaction types', function () {
        $validTypes = ['earn', 'burn', 'expire', 'adjustment', 'refund', 'bonus'];
        
        foreach ($validTypes as $type) {
            $transaction = createTestTransaction(['transaction_type' => $type]);
            expect($transaction->transaction_type)->toBe($type);
        }
    });

    it('validates status values', function () {
        $validStatuses = ['pending', 'completed', 'failed', 'cancelled', 'reversed'];
        
        foreach ($validStatuses as $status) {
            $transaction = createTestTransaction(['status' => $status]);
            expect($transaction->status)->toBe($status);
        }
    });

    it('calculates points change correctly', function () {
        $earnTransaction = createTestTransaction([
            'transaction_type' => 'earn',
            'points_amount' => 50,
        ]);
        
        $burnTransaction = createTestTransaction([
            'transaction_type' => 'burn',
            'points_amount' => -25,
        ]);

        expect($earnTransaction->getPointsChange())->toBe(50)
            ->and($burnTransaction->getPointsChange())->toBe(-25);
    });

    it('can get transaction summary for member', function () {
        $member = createTestMember();
        
        createTestTransaction([
            'member_passkit_id' => $member->passkit_id,
            'transaction_type' => 'earn',
            'points_amount' => 100,
        ]);
        
        createTestTransaction([
            'member_passkit_id' => $member->passkit_id,
            'transaction_type' => 'burn',
            'points_amount' => -50,
        ]);

        $summary = PassKitTransaction::getTransactionSummary($member->passkit_id);

        expect($summary)->toHaveKeys(['total_earned', 'total_spent', 'transaction_count'])
            ->and($summary['total_earned'])->toBe(100)
            ->and($summary['total_spent'])->toBe(50)
            ->and($summary['transaction_count'])->toBe(2);
    });

    it('has unique passkit_transaction_id constraint', function () {
        $transactionId = 'unique_txn_id';
        
        createTestTransaction(['passkit_transaction_id' => $transactionId]);
        
        expect(function () use ($transactionId) {
            createTestTransaction(['passkit_transaction_id' => $transactionId]);
        })->toThrow(\Illuminate\Database\QueryException::class);
    });
});