<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(ShakewellAgency\PassKitLaravel\Tests\TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toHavePassKitStructure', function () {
    return $this->toHaveKeys(['id', 'passkit_id', 'name', 'status']);
});

expect()->extend('toBeValidPassKitId', function () {
    return $this->toMatch('/^[a-zA-Z0-9_-]+$/');
});

expect()->extend('toBeValidJsonResponse', function () {
    return $this->toHaveKeys(['success'])
        ->and($this->json())->toBeArray();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the amount of code you need to type.
|
*/

function createTestProgram(array $attributes = []): \ShakewellAgency\PassKitLaravel\Models\PassKitProgram
{
    return \ShakewellAgency\PassKitLaravel\Models\PassKitProgram::create(array_merge([
        'passkit_id' => 'test_program_' . uniqid(),
        'name' => 'Test Program',
        'description' => 'Test program description',
        'program_type' => 'membership',
        'status' => 'active',
        'account_id' => 1,
        'metadata' => [],
    ], $attributes));
}

function createTestTier(array $attributes = []): \ShakewellAgency\PassKitLaravel\Models\PassKitTier
{
    $program = createTestProgram();
    
    return \ShakewellAgency\PassKitLaravel\Models\PassKitTier::create(array_merge([
        'passkit_id' => 'test_tier_' . uniqid(),
        'name' => 'Test Tier',
        'description' => 'Test tier description',
        'program_id' => $program->id,
        'template_id' => 'test_template_' . uniqid(),
        'metadata' => [],
    ], $attributes));
}

function createTestMember(array $attributes = []): \ShakewellAgency\PassKitLaravel\Models\PassKitMember
{
    $program = createTestProgram();
    
    return \ShakewellAgency\PassKitLaravel\Models\PassKitMember::create(array_merge([
        'passkit_id' => 'test_member_' . uniqid(),
        'external_id' => 'user_' . uniqid(),
        'user_id' => 1,
        'account_id' => 1,
        'program_id' => $program->id,
        'tier_id' => 'test_tier',
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'points_balance' => 100,
        'status' => 'active',
        'enrolled_at' => now(),
    ], $attributes));
}

function createTestWalletPass(array $attributes = []): \ShakewellAgency\PassKitLaravel\Models\WalletPass
{
    return \ShakewellAgency\PassKitLaravel\Models\WalletPass::create(array_merge([
        'passkit_id' => 'test_pass_' . uniqid(),
        'user_id' => 1,
        'account_id' => 1,
        'pass_data' => [
            'points' => 100,
            'tier' => 'Bronze',
        ],
        'status' => 'active',
        'issued_at' => now(),
    ], $attributes));
}

function createTestTransaction(array $attributes = []): \ShakewellAgency\PassKitLaravel\Models\PassKitTransaction
{
    $member = createTestMember();
    
    return \ShakewellAgency\PassKitLaravel\Models\PassKitTransaction::create(array_merge([
        'passkit_transaction_id' => 'test_txn_' . uniqid(),
        'member_passkit_id' => $member->passkit_id,
        'member_id' => $member->id,
        'account_id' => 1,
        'transaction_type' => 'earn',
        'points_amount' => 50,
        'points_balance_before' => 100,
        'points_balance_after' => 150,
        'description' => 'Test transaction',
        'status' => 'completed',
        'processed_at' => now(),
    ], $attributes));
}

function createTestCardTemplate(array $attributes = []): \ShakewellAgency\PassKitLaravel\Models\CardTemplate
{
    return \ShakewellAgency\PassKitLaravel\Models\CardTemplate::create(array_merge([
        'passkit_template_id' => 'test_template_' . uniqid(),
        'name' => 'Test Template',
        'description' => 'Test template description',
        'template_type' => 'membership',
        'account_id' => 1,
        'is_active' => true,
        'template_data' => [],
        'field_definitions' => [],
        'design_settings' => [],
        'pass_settings' => [],
    ], $attributes));
}

function mockPassKitService(): \Mockery\MockInterface
{
    return Mockery::mock(\ShakewellAgency\PassKitLaravel\Services\PassKitService::class);
}

function createTestAuditLog(array $attributes = []): \ShakewellAgency\PassKitLaravel\Models\PassKitAuditLog
{
    return \ShakewellAgency\PassKitLaravel\Models\PassKitAuditLog::create(array_merge([
        'account_id' => 1,
        'event_type' => 'test_event',
        'entity_type' => 'test_entity',
        'entity_id' => '123',
        'user_id' => 1,
        'operation' => 'test_operation',
        'description' => 'Test audit log',
        'status' => 'success',
        'execution_time_ms' => 100.5,
        'correlation_id' => 'test_correlation_' . uniqid(),
        'security_level' => 'normal',
    ], $attributes));
}