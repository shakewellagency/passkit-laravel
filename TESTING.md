# PassKit Laravel Package Testing Guide

This document provides comprehensive information about the testing strategy, structure, and execution for the PassKit Laravel package.

## Overview

The PassKit Laravel package includes a complete test suite covering:
- **Unit Tests**: Individual component testing
- **Feature Tests**: API endpoint and controller testing  
- **Integration Tests**: End-to-end workflow testing
- **Database Tests**: Migration and schema validation

## Test Structure

```
tests/
├── TestCase.php                          # Base test case with shared setup
├── Pest.php                             # Pest configuration and helpers
├── Unit/                                # Unit tests
│   ├── Models/                          # Model testing
│   │   ├── PassKitProgramTest.php       # Program model tests
│   │   ├── PassKitMemberTest.php        # Member model tests
│   │   ├── PassKitTransactionTest.php   # Transaction model tests
│   │   ├── WalletPassTest.php           # Wallet pass model tests
│   │   └── PassKitAuditLogTest.php      # Audit log model tests
│   ├── Services/                        # Service layer testing
│   │   ├── PassKitServiceTest.php       # Core PassKit service
│   │   ├── PassKitCrudManagerTest.php   # CRUD operations
│   │   ├── PassKitSyncServiceTest.php   # Synchronization service
│   │   └── PassKitAuditServiceTest.php  # Audit logging service
│   ├── Console/                         # Console command testing
│   │   ├── PassKitSetupCommandTest.php  # Setup command tests
│   │   └── PassKitSyncCommandTest.php   # Sync command tests
│   └── Database/                        # Database testing
│       └── MigrationsTest.php           # Migration validation
├── Feature/                             # Feature tests
│   └── Controllers/                     # Controller testing
│       └── PassKitControllerTest.php    # API endpoint tests
├── Integration/                         # Integration tests
│   └── PassKitWorkflowTest.php          # End-to-end workflows
└── run-tests.sh                         # Test runner script
```

## Test Categories

### Unit Tests

#### Model Tests
- **PassKitProgram**: Program management, validation, relationships
- **PassKitMember**: Member lifecycle, points management, scopes
- **PassKitTransaction**: Transaction processing, status management, calculations
- **WalletPass**: Pass lifecycle, installation tracking, data management
- **PassKitAuditLog**: Audit trail, compliance tracking, performance monitoring

#### Service Tests
- **PassKitService**: Core API integration, member management, pass generation
- **PassKitCrudManager**: Database operations, batch processing, search functionality
- **PassKitSyncService**: Bi-directional sync, conflict resolution, error handling
- **PassKitAuditService**: Security logging, performance tracking, compliance reporting

#### Console Command Tests
- **PassKitSetup**: Package configuration, certificate management, validation
- **PassKitSync**: Data synchronization, batch operations, scheduling

#### Database Tests
- **Migrations**: Schema validation, relationships, indexes, constraints

### Feature Tests

#### API Controller Tests
- **Program Management**: CRUD operations, validation, authorization
- **Member Management**: Enrollment, points updates, search, analytics
- **Transaction Management**: Processing, status updates, reporting
- **Wallet Pass Management**: Creation, installation, updates, URLs
- **Synchronization**: API sync, status monitoring, conflict resolution
- **Analytics**: Statistics, performance metrics, compliance reports

### Integration Tests

#### Complete Workflows
- **Member Lifecycle**: Enrollment → Points → Transactions → Sync
- **Points Management**: Earning, spending, expiration, balance tracking
- **Synchronization**: Local ↔ PassKit API data consistency
- **Error Handling**: Partial failures, retry logic, recovery
- **Performance Monitoring**: Metrics collection, audit trails
- **Data Consistency**: Related entity updates, referential integrity

## Running Tests

### Prerequisites

1. **Laravel Environment**: The package must be installed in a Laravel project
2. **Database**: SQLite in-memory database for testing
3. **PHPUnit**: Available through Laravel's vendor directory
4. **Mockery**: For API mocking and service isolation

### Test Execution

#### Run All Tests
```bash
# From package directory
./run-tests.sh

# Or using PHPUnit directly
../../../vendor/bin/phpunit
```

#### Run Specific Test Suites
```bash
# Unit tests only
../../../vendor/bin/phpunit tests/Unit

# Feature tests only  
../../../vendor/bin/phpunit tests/Feature

# Integration tests only
../../../vendor/bin/phpunit tests/Integration

# Specific test file
../../../vendor/bin/phpunit tests/Unit/Models/PassKitMemberTest.php
```

#### Run with Coverage
```bash
../../../vendor/bin/phpunit --coverage-html coverage-report
```

#### Run with Filtering
```bash
# Run specific test method
../../../vendor/bin/phpunit --filter="test_can_create_member"

# Run tests matching pattern
../../../vendor/bin/phpunit --filter="Member"
```

## Test Helpers and Utilities

### Custom Expectations
```php
expect($program)->toHavePassKitStructure();
expect($email)->toBeValidEmail();
expect($uuid)->toBeValidUuid();
expect($response)->toHaveValidationError('email');
```

### Factory Functions
```php
// Create test data
$program = createTestProgram(['name' => 'VIP Program']);
$member = createTestMember(['points_balance' => 500]);
$transaction = createTestTransaction(['points_amount' => 100]);
$pass = createTestWalletPass(['status' => 'active']);
$auditLog = createTestAuditLog(['event_type' => 'member_created']);

// Mock API responses
$memberData = mockPassKitMemberData(['points' => 250]);
$transactionData = mockPassKitTransactionData(['type' => 'earn']);
```

### Database Assertions
```php
assertDatabaseHasPassKitMember(['email' => 'test@example.com']);
assertDatabaseMissingPassKitMember(['status' => 'deleted']);
assertValidPassKitId($member->passkit_id);
```

## Mocking Strategy

### PassKit API Mocking
```php
// Mock service responses
$this->mockPassKitService = Mockery::mock(PassKitService::class);
$this->mockPassKitService->shouldReceive('enrollMember')
    ->once()
    ->andReturn(['id' => 'member_123']);

// Mock gRPC client
$mockClient = Mockery::mock();
$mockClient->shouldReceive('getMember')
    ->with('member_123')
    ->andReturn([$memberData, (object) ['code' => 0]]);
```

### Database Mocking
```php
// Use SQLite in-memory database
use RefreshDatabase;

// Clean state for each test
beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
});
```

## Test Data Management

### Test Database Setup
- **SQLite In-Memory**: Fast, isolated, automatically cleaned
- **Migration Loading**: All package migrations run before tests
- **Factory Data**: Consistent test data creation
- **Transaction Rollback**: Each test runs in isolation

### Test Data Patterns
```php
// Arrange - Create test data
$program = createTestProgram(['account_id' => 1]);
$member = createTestMember(['program_id' => $program->id]);

// Act - Perform operation
$result = $this->crudManager->updateMember($member->id, ['points_balance' => 200]);

// Assert - Verify outcome
expect($result->points_balance)->toBe(200);
expect($result->last_sync_at)->not->toBeNull();
```

## Performance Testing

### Timing Assertions
```php
$startTime = microtime(true);
$result = $this->syncService->syncAllMembers(1);
$executionTime = (microtime(true) - $startTime) * 1000;

expect($executionTime)->toBeLessThan(1000); // Under 1 second
```

### Memory Usage
```php
$initialMemory = memory_get_usage();
$this->crudManager->batchCreateMembers($largeDataset);
$memoryUsed = memory_get_usage() - $initialMemory;

expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024); // Under 50MB
```

### Batch Operation Testing
```php
// Test with large datasets
for ($i = 0; $i < 1000; $i++) {
    $members[] = createTestMember(['external_id' => "user_{$i}"]);
}

$results = $this->syncService->syncAllMembers(1, ['batch_size' => 100]);
expect($results['synced'])->toBe(1000);
```

## Error Testing

### Exception Handling
```php
$this->mockPassKitService->shouldReceive('getMember')
    ->andThrow(new \Exception('API Error'));

expect(function () {
    $this->syncService->syncMemberFromApi('invalid_id', 1);
})->toThrow(\Exception::class);
```

### Validation Testing
```php
expect(function () {
    createTestMember(['email' => 'invalid-email']);
})->toThrow(\Illuminate\Database\QueryException::class);
```

### Partial Failure Testing
```php
// Test resilience to partial failures
$this->mockPassKitService->shouldReceive('getMember')
    ->times(3)
    ->andReturn($successData)
    ->andThrow(new \Exception('Member 2 failed'))
    ->andReturn($successData);

$results = $this->syncService->syncAllMembers(1);
expect($results['synced'])->toBe(2);
expect($results['failed'])->toBe(1);
```

## Security Testing

### Input Validation
```php
$maliciousData = [
    'email' => '<script>alert("xss")</script>',
    'name' => str_repeat('A', 1000),
    'points_balance' => -999999,
];

expect(function () use ($maliciousData) {
    $this->crudManager->createMember($maliciousData);
})->toThrow(\Exception::class);
```

### SQL Injection Prevention
```php
$member = createTestMember(['email' => "'; DROP TABLE members; --"]);
expect($member->email)->toBe("'; DROP TABLE members; --");
// Should be safely stored, not executed
```

### Authorization Testing
```php
$response = $this->getJson("/api/passkit/programs/{$otherAccountProgram->id}");
$response->assertStatus(403)
    ->assertJsonFragment(['message' => 'Unauthorized']);
```

## Continuous Integration

### GitHub Actions Integration
```yaml
name: PassKit Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
      - name: Install Dependencies
        run: composer install
      - name: Run Tests
        run: ./packages/shakewell/passkit-laravel/run-tests.sh
```

### Coverage Requirements
- **Unit Tests**: >90% line coverage
- **Feature Tests**: All API endpoints covered
- **Integration Tests**: Critical workflows covered
- **Overall**: >85% total coverage

## Best Practices

### Test Organization
1. **One Concept Per Test**: Each test should verify one specific behavior
2. **Descriptive Names**: Test names should clearly describe what's being tested
3. **Arrange-Act-Assert**: Follow the AAA pattern consistently
4. **Independent Tests**: Tests should not depend on each other

### Test Data
1. **Factory Functions**: Use helper functions for consistent test data
2. **Minimal Data**: Create only the data needed for each test
3. **Realistic Data**: Use realistic values and relationships
4. **Clean State**: Each test starts with a clean database state

### Mocking Guidelines
1. **Mock External Services**: Always mock PassKit API calls
2. **Don't Mock What You're Testing**: Mock dependencies, not the subject
3. **Verify Interactions**: Assert that mocked methods are called correctly
4. **Realistic Responses**: Mock responses should match real API behavior

### Performance
1. **Fast Tests**: Tests should run quickly for rapid feedback
2. **Parallel Execution**: Structure tests to run in parallel when possible
3. **Memory Efficient**: Clean up resources and avoid memory leaks
4. **Database Optimization**: Use in-memory database for speed

## Troubleshooting

### Common Issues

#### Test Database Not Found
```bash
# Ensure migrations are loaded
php artisan migrate --path=packages/shakewell/passkit-laravel/database/migrations
```

#### Mockery Errors
```php
// Always close Mockery in tearDown
afterEach(function () {
    Mockery::close();
});
```

#### Memory Limits
```php
// Increase memory limit for large dataset tests
ini_set('memory_limit', '512M');
```

#### Timing Issues
```php
// Use fake time for consistent testing
Carbon::setTestNow('2024-01-01 12:00:00');
```

### Debug Tools
```php
// Enable query logging
DB::enableQueryLog();
$queries = DB::getQueryLog();

// Dump test data
dump($member->toArray());

// Assert with debugging
expect($result)->toBe($expected, "Expected {$expected}, got {$result}");
```

## Contributing

When adding new tests:

1. **Follow Existing Patterns**: Use established naming and structure conventions
2. **Add Documentation**: Update this guide when adding new test types
3. **Maintain Coverage**: Ensure new features include comprehensive tests
4. **Update Helpers**: Add new factory functions and expectations as needed
5. **Performance Impact**: Consider test execution time and optimize accordingly

## Test Metrics

The test suite should maintain these metrics:

- **Execution Time**: Complete suite under 5 minutes
- **Test Count**: 200+ individual test cases
- **Coverage**: >85% overall code coverage
- **Reliability**: <1% flaky test rate
- **Maintainability**: Clear, readable, and well-documented tests