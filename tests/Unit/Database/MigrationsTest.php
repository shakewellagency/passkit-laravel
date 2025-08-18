<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

describe('PassKit Database Migrations', function () {
    it('creates passkit_programs table with correct structure', function () {
        expect(Schema::hasTable('passkit_programs'))->toBeTrue();

        $columns = Schema::getColumnListing('passkit_programs');
        
        expect($columns)->toContain('id')
            ->and($columns)->toContain('passkit_id')
            ->and($columns)->toContain('name')
            ->and($columns)->toContain('description')
            ->and($columns)->toContain('program_type')
            ->and($columns)->toContain('account_id')
            ->and($columns)->toContain('status')
            ->and($columns)->toContain('settings')
            ->and($columns)->toContain('created_at')
            ->and($columns)->toContain('updated_at');

        // Check unique indexes
        $indexes = Schema::getConnection()->getDoctrineSchemaManager()
            ->listTableIndexes('passkit_programs');
        
        expect($indexes)->toHaveKey('passkit_programs_passkit_id_unique');
    });

    it('creates passkit_tiers table with correct structure', function () {
        expect(Schema::hasTable('passkit_tiers'))->toBeTrue();

        $columns = Schema::getColumnListing('passkit_tiers');
        
        expect($columns)->toContain('id')
            ->and($columns)->toContain('passkit_id')
            ->and($columns)->toContain('program_id')
            ->and($columns)->toContain('name')
            ->and($columns)->toContain('description')
            ->and($columns)->toContain('benefits')
            ->and($columns)->toContain('requirements')
            ->and($columns)->toContain('sort_order');
    });

    it('creates passkit_members table with correct structure', function () {
        expect(Schema::hasTable('passkit_members'))->toBeTrue();

        $columns = Schema::getColumnListing('passkit_members');
        
        expect($columns)->toContain('id')
            ->and($columns)->toContain('passkit_id')
            ->and($columns)->toContain('external_id')
            ->and($columns)->toContain('program_id')
            ->and($columns)->toContain('tier_id')
            ->and($columns)->toContain('account_id')
            ->and($columns)->toContain('email')
            ->and($columns)->toContain('first_name')
            ->and($columns)->toContain('last_name')
            ->and($columns)->toContain('phone')
            ->and($columns)->toContain('date_of_birth')
            ->and($columns)->toContain('gender')
            ->and($columns)->toContain('address_line1')
            ->and($columns)->toContain('city')
            ->and($columns)->toContain('state')
            ->and($columns)->toContain('postal_code')
            ->and($columns)->toContain('country')
            ->and($columns)->toContain('points_balance')
            ->and($columns)->toContain('lifetime_points')
            ->and($columns)->toContain('status')
            ->and($columns)->toContain('enrolled_at')
            ->and($columns)->toContain('last_activity_at')
            ->and($columns)->toContain('preferences')
            ->and($columns)->toContain('custom_fields')
            ->and($columns)->toContain('tags')
            ->and($columns)->toContain('sync_pending')
            ->and($columns)->toContain('last_sync_at');

        // Check unique constraint
        $indexes = Schema::getConnection()->getDoctrineSchemaManager()
            ->listTableIndexes('passkit_members');
        
        expect($indexes)->toHaveKey('passkit_members_passkit_id_unique');
    });

    it('creates passkit_transactions table with correct structure', function () {
        expect(Schema::hasTable('passkit_transactions'))->toBeTrue();

        $columns = Schema::getColumnListing('passkit_transactions');
        
        expect($columns)->toContain('id')
            ->and($columns)->toContain('passkit_transaction_id')
            ->and($columns)->toContain('member_id')
            ->and($columns)->toContain('member_passkit_id')
            ->and($columns)->toContain('transaction_type')
            ->and($columns)->toContain('points_amount')
            ->and($columns)->toContain('points_balance_before')
            ->and($columns)->toContain('points_balance_after')
            ->and($columns)->toContain('description')
            ->and($columns)->toContain('reference_id')
            ->and($columns)->toContain('purchase_amount')
            ->and($columns)->toContain('points_multiplier')
            ->and($columns)->toContain('status')
            ->and($columns)->toContain('processed_at')
            ->and($columns)->toContain('expires_at')
            ->and($columns)->toContain('is_expired')
            ->and($columns)->toContain('expired_at')
            ->and($columns)->toContain('failure_reason')
            ->and($columns)->toContain('reversed_by_transaction_id')
            ->and($columns)->toContain('webhook_data')
            ->and($columns)->toContain('passkit_data');
    });

    it('creates wallet_passes table with correct structure', function () {
        expect(Schema::hasTable('wallet_passes'))->toBeTrue();

        $columns = Schema::getColumnListing('wallet_passes');
        
        expect($columns)->toContain('id')
            ->and($columns)->toContain('passkit_id')
            ->and($columns)->toContain('user_id')
            ->and($columns)->toContain('account_id')
            ->and($columns)->toContain('template_id')
            ->and($columns)->toContain('program_id')
            ->and($columns)->toContain('member_passkit_id')
            ->and($columns)->toContain('pass_data')
            ->and($columns)->toContain('field_values')
            ->and($columns)->toContain('barcode_data')
            ->and($columns)->toContain('status')
            ->and($columns)->toContain('is_installed')
            ->and($columns)->toContain('installed_at')
            ->and($columns)->toContain('device_type')
            ->and($columns)->toContain('device_model')
            ->and($columns)->toContain('device_id')
            ->and($columns)->toContain('install_urls')
            ->and($columns)->toContain('qr_codes')
            ->and($columns)->toContain('issued_at')
            ->and($columns)->toContain('expires_at')
            ->and($columns)->toContain('voided_at')
            ->and($columns)->toContain('voided_reason')
            ->and($columns)->toContain('update_count')
            ->and($columns)->toContain('view_count')
            ->and($columns)->toContain('share_count')
            ->and($columns)->toContain('last_viewed_at')
            ->and($columns)->toContain('last_updated_at')
            ->and($columns)->toContain('sharable')
            ->and($columns)->toContain('sync_pending')
            ->and($columns)->toContain('last_sync_at');
    });

    it('creates passkit_audit_logs table with correct structure', function () {
        expect(Schema::hasTable('passkit_audit_logs'))->toBeTrue();

        $columns = Schema::getColumnListing('passkit_audit_logs');
        
        expect($columns)->toContain('id')
            ->and($columns)->toContain('account_id')
            ->and($columns)->toContain('event_type')
            ->and($columns)->toContain('entity_type')
            ->and($columns)->toContain('entity_id')
            ->and($columns)->toContain('user_id')
            ->and($columns)->toContain('operation')
            ->and($columns)->toContain('status')
            ->and($columns)->toContain('description')
            ->and($columns)->toContain('old_values')
            ->and($columns)->toContain('new_values')
            ->and($columns)->toContain('error_details')
            ->and($columns)->toContain('execution_time_ms')
            ->and($columns)->toContain('passkit_response_time_ms')
            ->and($columns)->toContain('correlation_id')
            ->and($columns)->toContain('session_id')
            ->and($columns)->toContain('ip_address')
            ->and($columns)->toContain('user_agent')
            ->and($columns)->toContain('metadata')
            ->and($columns)->toContain('request_data')
            ->and($columns)->toContain('response_data')
            ->and($columns)->toContain('security_level')
            ->and($columns)->toContain('business_impact_score')
            ->and($columns)->toContain('data_validated')
            ->and($columns)->toContain('sensitive_data_masked')
            ->and($columns)->toContain('requires_approval')
            ->and($columns)->toContain('approved_by')
            ->and($columns)->toContain('approved_at')
            ->and($columns)->toContain('gdpr_relevant')
            ->and($columns)->toContain('pci_relevant')
            ->and($columns)->toContain('tags')
            ->and($columns)->toContain('expires_at')
            ->and($columns)->toContain('archived')
            ->and($columns)->toContain('archived_at')
            ->and($columns)->toContain('archive_reason');
    });

    it('creates passkit_security_logs table with correct structure', function () {
        expect(Schema::hasTable('passkit_security_logs'))->toBeTrue();

        $columns = Schema::getColumnListing('passkit_security_logs');
        
        expect($columns)->toContain('id')
            ->and($columns)->toContain('audit_log_id')
            ->and($columns)->toContain('account_id')
            ->and($columns)->toContain('security_event')
            ->and($columns)->toContain('threat_level')
            ->and($columns)->toContain('source_ip')
            ->and($columns)->toContain('source_country')
            ->and($columns)->toContain('user_agent')
            ->and($columns)->toContain('attack_vector')
            ->and($columns)->toContain('risk_indicators')
            ->and($columns)->toContain('affected_resources')
            ->and($columns)->toContain('mitigation_actions')
            ->and($columns)->toContain('false_positive')
            ->and($columns)->toContain('investigated_by')
            ->and($columns)->toContain('investigated_at')
            ->and($columns)->toContain('resolution_notes');
    });

    it('creates passkit_performance_logs table with correct structure', function () {
        expect(Schema::hasTable('passkit_performance_logs'))->toBeTrue();

        $columns = Schema::getColumnListing('passkit_performance_logs');
        
        expect($columns)->toContain('id')
            ->and($columns)->toContain('audit_log_id')
            ->and($columns)->toContain('account_id')
            ->and($columns)->toContain('operation_type')
            ->and($columns)->toContain('duration_ms')
            ->and($columns)->toContain('memory_usage_mb')
            ->and($columns)->toContain('cpu_usage_percent')
            ->and($columns)->toContain('api_calls_count')
            ->and($columns)->toContain('database_queries_count')
            ->and($columns)->toContain('cache_hits')
            ->and($columns)->toContain('cache_misses')
            ->and($columns)->toContain('performance_threshold_exceeded')
            ->and($columns)->toContain('performance_grade')
            ->and($columns)->toContain('performance_score')
            ->and($columns)->toContain('bottlenecks')
            ->and($columns)->toContain('optimization_suggestions');
    });

    it('has proper foreign key relationships', function () {
        // Test creating related records to verify relationships
        $program = createTestProgram(['account_id' => 1]);
        $tier = createTestTier(['program_id' => $program->id]);
        $member = createTestMember([
            'program_id' => $program->id,
            'tier_id' => $tier->passkit_id,
            'account_id' => 1,
        ]);
        $transaction = createTestTransaction([
            'member_id' => $member->id,
            'member_passkit_id' => $member->passkit_id,
        ]);
        $template = createTestCardTemplate(['account_id' => 1]);
        $pass = createTestWalletPass([
            'user_id' => 1,
            'account_id' => 1,
            'template_id' => $template->id,
            'program_id' => $program->id,
            'member_passkit_id' => $member->passkit_id,
        ]);

        // Verify relationships work
        expect($member->program->id)->toBe($program->id)
            ->and($member->transactions)->toHaveCount(1)
            ->and($pass->template->id)->toBe($template->id)
            ->and($pass->program->id)->toBe($program->id);
    });

    it('has proper indexes for performance', function () {
        $tables = [
            'passkit_programs' => ['account_id', 'status', 'passkit_id'],
            'passkit_members' => ['account_id', 'program_id', 'email', 'external_id', 'passkit_id'],
            'passkit_transactions' => ['member_id', 'member_passkit_id', 'transaction_type', 'status'],
            'wallet_passes' => ['user_id', 'account_id', 'member_passkit_id', 'status'],
            'passkit_audit_logs' => ['account_id', 'event_type', 'entity_type', 'created_at'],
        ];

        foreach ($tables as $table => $expectedIndexedColumns) {
            $indexes = Schema::getConnection()->getDoctrineSchemaManager()
                ->listTableIndexes($table);

            foreach ($expectedIndexedColumns as $column) {
                $hasIndex = collect($indexes)->contains(function ($index) use ($column) {
                    return in_array($column, $index->getColumns());
                });

                expect($hasIndex)->toBeTrue("Table {$table} should have index on column {$column}");
            }
        }
    });

    it('can rollback migrations cleanly', function () {
        // Get all PassKit tables
        $passkitTables = [
            'passkit_performance_logs',
            'passkit_security_logs',
            'passkit_audit_logs',
            'wallet_passes',
            'passkit_transactions',
            'passkit_members',
            'passkit_tiers',
            'passkit_programs',
        ];

        // Verify all tables exist
        foreach ($passkitTables as $table) {
            expect(Schema::hasTable($table))->toBeTrue();
        }

        // Run rollback
        $this->artisan('migrate:rollback', [
            '--path' => 'packages/shakewell/passkit-laravel/database/migrations',
        ]);

        // Verify tables were dropped
        foreach ($passkitTables as $table) {
            expect(Schema::hasTable($table))->toBeFalse();
        }

        // Re-run migrations for cleanup
        $this->artisan('migrate', [
            '--path' => 'packages/shakewell/passkit-laravel/database/migrations',
        ]);
    });

    it('handles migration dependencies correctly', function () {
        // Verify that dependent tables are created after their dependencies
        $migrationOrder = [
            'passkit_programs',
            'passkit_tiers',
            'passkit_members',
            'passkit_transactions',
            'wallet_passes',
            'passkit_audit_logs',
            'passkit_security_logs',
            'passkit_performance_logs',
        ];

        foreach ($migrationOrder as $table) {
            expect(Schema::hasTable($table))->toBeTrue("Table {$table} should exist");
        }
    });

    it('supports data types for JSON columns', function () {
        // Test JSON column functionality
        $member = createTestMember([
            'preferences' => ['language' => 'en', 'notifications' => true],
            'custom_fields' => ['favorite_color' => 'blue', 'interests' => ['tech', 'sports']],
            'tags' => ['vip', 'premium'],
        ]);

        expect($member->preferences)->toBeArray()
            ->and($member->preferences['language'])->toBe('en')
            ->and($member->custom_fields['interests'])->toContain('tech')
            ->and($member->tags)->toContain('vip');

        // Test complex JSON updates
        $member->update([
            'preferences' => array_merge($member->preferences, ['theme' => 'dark']),
        ]);

        $member->refresh();
        expect($member->preferences['theme'])->toBe('dark')
            ->and($member->preferences['language'])->toBe('en'); // Should preserve existing data
    });

    it('handles large dataset scenarios', function () {
        // Test with larger amounts of data to ensure constraints work
        $program = createTestProgram(['account_id' => 1]);
        
        // Create many members to test performance
        for ($i = 1; $i <= 100; $i++) {
            $member = createTestMember([
                'program_id' => $program->id,
                'account_id' => 1,
                'external_id' => "user_{$i}",
                'email' => "user{$i}@example.com",
                'passkit_id' => "member_{$i}",
            ]);

            // Create transactions for each member
            for ($j = 1; $j <= 5; $j++) {
                createTestTransaction([
                    'member_id' => $member->id,
                    'member_passkit_id' => $member->passkit_id,
                    'passkit_transaction_id' => "txn_{$i}_{$j}",
                    'transaction_type' => $j % 2 === 0 ? 'earn' : 'burn',
                    'points_amount' => $j % 2 === 0 ? 50 : -25,
                ]);
            }
        }

        // Verify data integrity
        expect(ShakewellAgency\PassKitLaravel\Models\PassKitMember::count())->toBe(100);
        expect(ShakewellAgency\PassKitLaravel\Models\PassKitTransaction::count())->toBe(500);

        // Test query performance with indexes
        $startTime = microtime(true);
        $activeMembers = ShakewellAgency\PassKitLaravel\Models\PassKitMember::where('account_id', 1)
            ->where('status', 'active')
            ->count();
        $queryTime = microtime(true) - $startTime;

        expect($activeMembers)->toBe(100)
            ->and($queryTime)->toBeLessThan(0.1); // Should be fast with proper indexing
    });
});