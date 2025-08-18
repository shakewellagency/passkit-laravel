<?php

use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;
use ShakewellAgency\PassKitLaravel\Models\PassKitTier;

describe('PassKitProgram Model', function () {
    it('can create a program', function () {
        $program = createTestProgram([
            'name' => 'VIP Membership',
            'description' => 'Exclusive VIP benefits',
            'program_type' => 'membership',
        ]);

        expect($program)->toBeInstanceOf(PassKitProgram::class)
            ->and($program->name)->toBe('VIP Membership')
            ->and($program->description)->toBe('Exclusive VIP benefits')
            ->and($program->program_type)->toBe('membership')
            ->and($program->status)->toBe('active');
    });

    it('has required fillable attributes', function () {
        $program = new PassKitProgram();
        $fillable = $program->getFillable();

        expect($fillable)->toContain('passkit_id')
            ->and($fillable)->toContain('name')
            ->and($fillable)->toContain('description')
            ->and($fillable)->toContain('program_type')
            ->and($fillable)->toContain('status')
            ->and($fillable)->toContain('metadata')
            ->and($fillable)->toContain('account_id');
    });

    it('casts metadata to array', function () {
        $program = createTestProgram([
            'metadata' => ['test' => 'value'],
        ]);

        expect($program->metadata)->toBeArray()
            ->and($program->metadata['test'])->toBe('value');
    });

    it('has tiers relationship', function () {
        $program = createTestProgram();
        $tier = createTestTier(['program_id' => $program->id]);

        expect($program->tiers)->toHaveCount(1)
            ->and($program->tiers->first())->toBeInstanceOf(PassKitTier::class)
            ->and($program->tiers->first()->id)->toBe($tier->id);
    });

    it('can scope by account', function () {
        createTestProgram(['account_id' => 1]);
        createTestProgram(['account_id' => 2]);

        $programs = PassKitProgram::byAccount(1)->get();

        expect($programs)->toHaveCount(1)
            ->and($programs->first()->account_id)->toBe(1);
    });

    it('can scope by type', function () {
        createTestProgram(['program_type' => 'membership']);
        createTestProgram(['program_type' => 'event_ticket']);

        $membershipPrograms = PassKitProgram::byType('membership')->get();

        expect($membershipPrograms)->toHaveCount(1)
            ->and($membershipPrograms->first()->program_type)->toBe('membership');
    });

    it('can scope by status', function () {
        createTestProgram(['status' => 'active']);
        createTestProgram(['status' => 'inactive']);

        $activePrograms = PassKitProgram::active()->get();

        expect($activePrograms)->toHaveCount(1)
            ->and($activePrograms->first()->status)->toBe('active');
    });

    it('has is_active accessor', function () {
        $activeProgram = createTestProgram(['status' => 'active']);
        $inactiveProgram = createTestProgram(['status' => 'inactive']);

        expect($activeProgram->is_active)->toBeTrue()
            ->and($inactiveProgram->is_active)->toBeFalse();
    });

    it('can activate and deactivate', function () {
        $program = createTestProgram(['status' => 'inactive']);

        $program->activate();
        expect($program->status)->toBe('active');

        $program->deactivate();
        expect($program->status)->toBe('inactive');
    });

    it('can get tiers count', function () {
        $program = createTestProgram();
        createTestTier(['program_id' => $program->id]);
        createTestTier(['program_id' => $program->id]);

        expect($program->getTiersCount())->toBe(2);
    });

    it('validates program types', function () {
        $validTypes = ['membership', 'event_ticket', 'coupon'];
        
        foreach ($validTypes as $type) {
            $program = createTestProgram(['program_type' => $type]);
            expect($program->program_type)->toBe($type);
        }
    });

    it('validates status values', function () {
        $validStatuses = ['active', 'inactive', 'draft'];
        
        foreach ($validStatuses as $status) {
            $program = createTestProgram(['status' => $status]);
            expect($program->status)->toBe($status);
        }
    });

    it('has unique passkit_id constraint', function () {
        $passkitId = 'unique_program_id';
        
        createTestProgram(['passkit_id' => $passkitId]);
        
        expect(function () use ($passkitId) {
            createTestProgram(['passkit_id' => $passkitId]);
        })->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('can find by passkit_id', function () {
        $program = createTestProgram(['passkit_id' => 'test_program_123']);

        $found = PassKitProgram::where('passkit_id', 'test_program_123')->first();

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($program->id);
    });
});