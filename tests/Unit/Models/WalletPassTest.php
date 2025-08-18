<?php

use ShakewellAgency\PassKitLaravel\Models\WalletPass;
use ShakewellAgency\PassKitLaravel\Models\CardTemplate;
use ShakewellAgency\PassKitLaravel\Models\PassKitProgram;

describe('WalletPass Model', function () {
    it('can create a wallet pass', function () {
        $pass = createTestWalletPass([
            'pass_data' => ['points' => 250, 'tier' => 'Gold'],
            'status' => 'active',
            'is_installed' => true,
        ]);

        expect($pass)->toBeInstanceOf(WalletPass::class)
            ->and($pass->pass_data)->toBeArray()
            ->and($pass->pass_data['points'])->toBe(250)
            ->and($pass->status)->toBe('active')
            ->and($pass->is_installed)->toBeTrue();
    });

    it('has required fillable attributes', function () {
        $pass = new WalletPass();
        $fillable = $pass->getFillable();

        expect($fillable)->toContain('passkit_id')
            ->and($fillable)->toContain('user_id')
            ->and($fillable)->toContain('account_id')
            ->and($fillable)->toContain('pass_data')
            ->and($fillable)->toContain('status')
            ->and($fillable)->toContain('is_installed')
            ->and($fillable)->toContain('issued_at');
    });

    it('casts dates correctly', function () {
        $pass = createTestWalletPass([
            'issued_at' => '2024-01-15 10:30:00',
            'installed_at' => '2024-01-16 11:45:00',
            'expires_at' => '2024-12-31 23:59:59',
        ]);

        expect($pass->issued_at)->toBeInstanceOf(Carbon\Carbon::class)
            ->and($pass->installed_at)->toBeInstanceOf(Carbon\Carbon::class)
            ->and($pass->expires_at)->toBeInstanceOf(Carbon\Carbon::class);
    });

    it('casts JSON fields to arrays', function () {
        $pass = createTestWalletPass([
            'pass_data' => ['points' => 100, 'tier' => 'Bronze'],
            'field_values' => ['name' => 'John Doe', 'email' => 'john@example.com'],
            'barcode_data' => ['format' => 'qr', 'value' => '12345'],
            'install_urls' => ['apple' => 'https://wallet.apple.com/...'],
            'qr_codes' => ['apple' => 'data:image/png;base64,...'],
        ]);

        expect($pass->pass_data)->toBeArray()
            ->and($pass->field_values)->toBeArray()
            ->and($pass->barcode_data)->toBeArray()
            ->and($pass->install_urls)->toBeArray()
            ->and($pass->qr_codes)->toBeArray()
            ->and($pass->pass_data['points'])->toBe(100);
    });

    it('casts boolean fields correctly', function () {
        $pass = createTestWalletPass([
            'is_installed' => true,
            'sharable' => false,
            'sync_pending' => true,
        ]);

        expect($pass->is_installed)->toBeTrue()
            ->and($pass->sharable)->toBeFalse()
            ->and($pass->sync_pending)->toBeTrue();
    });

    it('has template relationship', function () {
        $template = createTestCardTemplate();
        $pass = createTestWalletPass(['template_id' => $template->id]);

        expect($pass->template)->toBeInstanceOf(CardTemplate::class)
            ->and($pass->template->id)->toBe($template->id);
    });

    it('has program relationship', function () {
        $program = createTestProgram();
        $pass = createTestWalletPass(['program_id' => $program->id]);

        expect($pass->program)->toBeInstanceOf(PassKitProgram::class)
            ->and($pass->program->id)->toBe($program->id);
    });

    it('can scope by user', function () {
        createTestWalletPass(['user_id' => 1]);
        createTestWalletPass(['user_id' => 2]);

        $userPasses = WalletPass::byUser(1)->get();

        expect($userPasses)->toHaveCount(1)
            ->and($userPasses->first()->user_id)->toBe(1);
    });

    it('can scope by account', function () {
        createTestWalletPass(['account_id' => 1]);
        createTestWalletPass(['account_id' => 2]);

        $accountPasses = WalletPass::byAccount(1)->get();

        expect($accountPasses)->toHaveCount(1)
            ->and($accountPasses->first()->account_id)->toBe(1);
    });

    it('can scope by status', function () {
        createTestWalletPass(['status' => 'active']);
        createTestWalletPass(['status' => 'inactive']);

        $activePasses = WalletPass::active()->get();

        expect($activePasses)->toHaveCount(1)
            ->and($activePasses->first()->status)->toBe('active');
    });

    it('can scope installed passes', function () {
        createTestWalletPass(['is_installed' => true]);
        createTestWalletPass(['is_installed' => false]);

        $installedPasses = WalletPass::installed()->get();

        expect($installedPasses)->toHaveCount(1)
            ->and($installedPasses->first()->is_installed)->toBeTrue();
    });

    it('can scope by device type', function () {
        createTestWalletPass(['device_type' => 'ios']);
        createTestWalletPass(['device_type' => 'android']);

        $iosPasses = WalletPass::byDeviceType('ios')->get();

        expect($iosPasses)->toHaveCount(1)
            ->and($iosPasses->first()->device_type)->toBe('ios');
    });

    it('can scope by member', function () {
        createTestWalletPass(['member_passkit_id' => 'member_123']);
        createTestWalletPass(['member_passkit_id' => 'member_456']);

        $memberPasses = WalletPass::byMember('member_123')->get();

        expect($memberPasses)->toHaveCount(1)
            ->and($memberPasses->first()->member_passkit_id)->toBe('member_123');
    });

    it('can scope expired passes', function () {
        createTestWalletPass([
            'expires_at' => now()->subDays(1),
            'status' => 'expired',
        ]);
        createTestWalletPass([
            'expires_at' => now()->addDays(1),
            'status' => 'active',
        ]);

        $expiredPasses = WalletPass::expired()->get();

        expect($expiredPasses)->toHaveCount(1)
            ->and($expiredPasses->first()->status)->toBe('expired');
    });

    it('has is_active accessor', function () {
        $activePass = createTestWalletPass(['status' => 'active']);
        $inactivePass = createTestWalletPass(['status' => 'inactive']);

        expect($activePass->is_active)->toBeTrue()
            ->and($inactivePass->is_active)->toBeFalse();
    });

    it('has is_expired accessor', function () {
        $expiredPass = createTestWalletPass(['expires_at' => now()->subDays(1)]);
        $validPass = createTestWalletPass(['expires_at' => now()->addDays(1)]);

        expect($expiredPass->is_expired)->toBeTrue()
            ->and($validPass->is_expired)->toBeFalse();
    });

    it('has days_until_expiry accessor', function () {
        $pass = createTestWalletPass(['expires_at' => now()->addDays(10)]);

        expect($pass->days_until_expiry)->toBe(10);
    });

    it('has current_points accessor', function () {
        $pass = createTestWalletPass([
            'pass_data' => ['points' => 350, 'tier' => 'Gold'],
        ]);

        expect($pass->current_points)->toBe(350);
    });

    it('has current_tier accessor', function () {
        $pass = createTestWalletPass([
            'pass_data' => ['points' => 350, 'tier' => 'Gold'],
        ]);

        expect($pass->current_tier)->toBe('Gold');
    });

    it('can activate pass', function () {
        $pass = createTestWalletPass(['status' => 'inactive']);

        $pass->activate();

        expect($pass->status)->toBe('active');
    });

    it('can deactivate pass', function () {
        $pass = createTestWalletPass(['status' => 'active']);

        $pass->deactivate();

        expect($pass->status)->toBe('inactive');
    });

    it('can suspend pass', function () {
        $pass = createTestWalletPass(['status' => 'active']);

        $pass->suspend();

        expect($pass->status)->toBe('suspended');
    });

    it('can void pass', function () {
        $pass = createTestWalletPass(['status' => 'active']);

        $pass->void('User requested cancellation');

        expect($pass->status)->toBe('voided')
            ->and($pass->voided_reason)->toBe('User requested cancellation')
            ->and($pass->voided_at)->not->toBeNull();
    });

    it('can mark as installed', function () {
        $pass = createTestWalletPass(['is_installed' => false]);

        $pass->markAsInstalled('ios', 'iPhone 15 Pro');

        expect($pass->is_installed)->toBeTrue()
            ->and($pass->installed_at)->not->toBeNull()
            ->and($pass->device_type)->toBe('ios')
            ->and($pass->device_model)->toBe('iPhone 15 Pro');
    });

    it('can update pass data', function () {
        $pass = createTestWalletPass([
            'pass_data' => ['points' => 100, 'tier' => 'Bronze'],
        ]);

        $pass->updatePassData(['points' => 200, 'tier' => 'Silver']);

        expect($pass->pass_data['points'])->toBe(200)
            ->and($pass->pass_data['tier'])->toBe('Silver')
            ->and($pass->last_updated_at)->not->toBeNull();
    });

    it('can update points', function () {
        $pass = createTestWalletPass([
            'pass_data' => ['points' => 100],
        ]);

        $pass->updatePoints(250);

        expect($pass->pass_data['points'])->toBe(250)
            ->and($pass->last_updated_at)->not->toBeNull();
    });

    it('can increment update count', function () {
        $pass = createTestWalletPass(['update_count' => 5]);

        $pass->incrementUpdateCount();

        expect($pass->update_count)->toBe(6);
    });

    it('can record view', function () {
        $pass = createTestWalletPass(['view_count' => 10]);

        $pass->recordView();

        expect($pass->view_count)->toBe(11)
            ->and($pass->last_viewed_at)->not->toBeNull();
    });

    it('can record share', function () {
        $pass = createTestWalletPass(['share_count' => 2]);

        $pass->recordShare();

        expect($pass->share_count)->toBe(3);
    });

    it('can set sync pending', function () {
        $pass = createTestWalletPass(['sync_pending' => false]);

        $pass->setSyncPending();

        expect($pass->sync_pending)->toBeTrue();
    });

    it('can clear sync pending', function () {
        $pass = createTestWalletPass(['sync_pending' => true]);

        $pass->clearSyncPending();

        expect($pass->sync_pending)->toBeFalse()
            ->and($pass->last_sync_at)->not->toBeNull();
    });

    it('validates status values', function () {
        $validStatuses = ['active', 'inactive', 'suspended', 'expired', 'voided'];
        
        foreach ($validStatuses as $status) {
            $pass = createTestWalletPass(['status' => $status]);
            expect($pass->status)->toBe($status);
        }
    });

    it('validates device types', function () {
        $validDeviceTypes = ['ios', 'android', 'web'];
        
        foreach ($validDeviceTypes as $deviceType) {
            $pass = createTestWalletPass(['device_type' => $deviceType]);
            expect($pass->device_type)->toBe($deviceType);
        }
    });

    it('has unique passkit_id constraint', function () {
        $passkitId = 'unique_pass_id';
        
        createTestWalletPass(['passkit_id' => $passkitId]);
        
        expect(function () use ($passkitId) {
            createTestWalletPass(['passkit_id' => $passkitId]);
        })->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('can get passes by user and account', function () {
        createTestWalletPass(['user_id' => 1, 'account_id' => 1]);
        createTestWalletPass(['user_id' => 1, 'account_id' => 2]);
        createTestWalletPass(['user_id' => 2, 'account_id' => 1]);

        $userAccountPasses = WalletPass::where('user_id', 1)
            ->where('account_id', 1)
            ->get();

        expect($userAccountPasses)->toHaveCount(1);
    });
});