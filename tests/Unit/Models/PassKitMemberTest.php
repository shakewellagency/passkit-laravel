<?php

use ShakewellAgency\PassKitLaravel\Models\PassKitMember;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitTransaction;

describe('PassKitMember Model', function () {
    it('can create a member', function () {
        $member = createTestMember([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'points_balance' => 250,
        ]);

        expect($member)->toBeInstanceOf(PassKitMember::class)
            ->and($member->email)->toBe('test@example.com')
            ->and($member->first_name)->toBe('John')
            ->and($member->last_name)->toBe('Doe')
            ->and($member->points_balance)->toBe(250);
    });

    it('has required fillable attributes', function () {
        $member = new PassKitMember();
        $fillable = $member->getFillable();

        expect($fillable)->toContain('passkit_id')
            ->and($fillable)->toContain('external_id')
            ->and($fillable)->toContain('email')
            ->and($fillable)->toContain('first_name')
            ->and($fillable)->toContain('last_name')
            ->and($fillable)->toContain('points_balance')
            ->and($fillable)->toContain('status')
            ->and($fillable)->toContain('enrolled_at');
    });

    it('casts dates correctly', function () {
        $member = createTestMember([
            'enrolled_at' => '2024-01-15 10:30:00',
            'date_of_birth' => '1990-05-15',
        ]);

        expect($member->enrolled_at)->toBeInstanceOf(Carbon\Carbon::class)
            ->and($member->date_of_birth)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('casts JSON fields to arrays', function () {
        $member = createTestMember([
            'preferences' => ['language' => 'en', 'notifications' => true],
            'custom_fields' => ['favorite_color' => 'blue'],
            'tags' => ['vip', 'premium'],
        ]);

        expect($member->preferences)->toBeArray()
            ->and($member->custom_fields)->toBeArray()
            ->and($member->tags)->toBeArray()
            ->and($member->preferences['language'])->toBe('en')
            ->and($member->tags)->toContain('vip');
    });

    it('has program relationship', function () {
        $program = createTestProgram();
        $member = createTestMember(['program_id' => $program->id]);

        expect($member->program)->toBeInstanceOf(PassKitProgram::class)
            ->and($member->program->id)->toBe($program->id);
    });

    it('has transactions relationship', function () {
        $member = createTestMember();
        $transaction = createTestTransaction([
            'member_id' => $member->id,
            'member_passkit_id' => $member->passkit_id,
        ]);

        expect($member->transactions)->toHaveCount(1)
            ->and($member->transactions->first())->toBeInstanceOf(PassKitTransaction::class)
            ->and($member->transactions->first()->id)->toBe($transaction->id);
    });

    it('can scope by account', function () {
        createTestMember(['account_id' => 1]);
        createTestMember(['account_id' => 2]);

        $members = PassKitMember::byAccount(1)->get();

        expect($members)->toHaveCount(1)
            ->and($members->first()->account_id)->toBe(1);
    });

    it('can scope by status', function () {
        createTestMember(['status' => 'active']);
        createTestMember(['status' => 'inactive']);

        $activeMembers = PassKitMember::active()->get();

        expect($activeMembers)->toHaveCount(1)
            ->and($activeMembers->first()->status)->toBe('active');
    });

    it('can scope by program', function () {
        $program1 = createTestProgram();
        $program2 = createTestProgram();
        
        createTestMember(['program_id' => $program1->id]);
        createTestMember(['program_id' => $program2->id]);

        $members = PassKitMember::byProgram($program1->id)->get();

        expect($members)->toHaveCount(1)
            ->and($members->first()->program_id)->toBe($program1->id);
    });

    it('can scope by tier', function () {
        createTestMember(['tier_id' => 'gold']);
        createTestMember(['tier_id' => 'silver']);

        $goldMembers = PassKitMember::byTier('gold')->get();

        expect($goldMembers)->toHaveCount(1)
            ->and($goldMembers->first()->tier_id)->toBe('gold');
    });

    it('can scope by enrollment date', function () {
        createTestMember(['enrolled_at' => now()->subDays(5)]);
        createTestMember(['enrolled_at' => now()->subDays(15)]);

        $recentMembers = PassKitMember::enrolledAfter(now()->subDays(10))->get();

        expect($recentMembers)->toHaveCount(1);
    });

    it('can scope by points range', function () {
        createTestMember(['points_balance' => 150]);
        createTestMember(['points_balance' => 250]);
        createTestMember(['points_balance' => 350]);

        $midRangeMembers = PassKitMember::pointsBetween(200, 300)->get();

        expect($midRangeMembers)->toHaveCount(1)
            ->and($midRangeMembers->first()->points_balance)->toBe(250);
    });

    it('has full_name accessor', function () {
        $member = createTestMember([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        expect($member->full_name)->toBe('John Doe');
    });

    it('has is_active accessor', function () {
        $activeMember = createTestMember(['status' => 'active']);
        $inactiveMember = createTestMember(['status' => 'inactive']);

        expect($activeMember->is_active)->toBeTrue()
            ->and($inactiveMember->is_active)->toBeFalse();
    });

    it('has days_since_enrollment accessor', function () {
        $member = createTestMember(['enrolled_at' => now()->subDays(30)]);

        expect($member->days_since_enrollment)->toBe(30);
    });

    it('can activate and deactivate', function () {
        $member = createTestMember(['status' => 'inactive']);

        $member->activate();
        expect($member->status)->toBe('active');

        $member->deactivate();
        expect($member->status)->toBe('inactive');
    });

    it('can add points', function () {
        $member = createTestMember(['points_balance' => 100]);

        $member->addPoints(50);

        expect($member->points_balance)->toBe(150);
    });

    it('can subtract points', function () {
        $member = createTestMember(['points_balance' => 100]);

        $member->subtractPoints(30);

        expect($member->points_balance)->toBe(70);
    });

    it('cannot subtract more points than available', function () {
        $member = createTestMember(['points_balance' => 50]);

        expect(function () use ($member) {
            $member->subtractPoints(100);
        })->toThrow(\InvalidArgumentException::class);
    });

    it('can update last activity', function () {
        $member = createTestMember(['last_activity_at' => null]);

        $member->updateLastActivity();

        expect($member->last_activity_at)->not->toBeNull()
            ->and($member->last_activity_at)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('can get total earned points', function () {
        $member = createTestMember();
        
        createTestTransaction([
            'member_id' => $member->id,
            'transaction_type' => 'earn',
            'points_amount' => 50,
        ]);
        
        createTestTransaction([
            'member_id' => $member->id,
            'transaction_type' => 'earn',
            'points_amount' => 30,
        ]);

        expect($member->getTotalEarnedPoints())->toBe(80);
    });

    it('can get total spent points', function () {
        $member = createTestMember();
        
        createTestTransaction([
            'member_id' => $member->id,
            'transaction_type' => 'burn',
            'points_amount' => -25,
        ]);
        
        createTestTransaction([
            'member_id' => $member->id,
            'transaction_type' => 'burn',
            'points_amount' => -15,
        ]);

        expect($member->getTotalSpentPoints())->toBe(40);
    });

    it('validates email format in custom validation', function () {
        $member = createTestMember(['email' => 'valid@example.com']);
        expect($member->email)->toBe('valid@example.com');
    });

    it('has unique passkit_id constraint', function () {
        $passkitId = 'unique_member_id';
        
        createTestMember(['passkit_id' => $passkitId]);
        
        expect(function () use ($passkitId) {
            createTestMember(['passkit_id' => $passkitId]);
        })->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('can find by external_id and account', function () {
        $member = createTestMember([
            'external_id' => 'user_123',
            'account_id' => 1,
        ]);

        $found = PassKitMember::where('external_id', 'user_123')
            ->where('account_id', 1)
            ->first();

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($member->id);
    });
});